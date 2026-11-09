<?php

namespace App\Services\Discovery;

use App\Models\DiscoveryFeedback;
use App\Models\Interest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Single authority for "who may appear in discovery" (target side) and
 * what a viewer may see. Every discovery read path funnels through here
 * so eligibility cannot drift between endpoints.
 *
 * Conditions are written against the users table so they can be applied
 * both to the eloquent listing query (with joins for search/order) and to
 * EXISTS fragments inside SQL aggregates without duplicating rules.
 */
class DiscoveryEligibilityService
{
    public function __construct(private readonly bool $enforceAdultsOnly = true) {}

    /**
     * Eloquent query of users eligible to appear in discovery for the
     * viewer. `user_profiles`, `user_privacy_settings` and
     * `discovery_preferences` are joined (aliased `up`, `ups`, `dp`) so
     * callers can add target-column conditions and ordering, but rows are
     * only eligible when the underlying tables exist AND all rules pass.
     */
    public function eligibleQuery(User $viewer, bool $forRecommendations = false): EloquentBuilder
    {
        $query = User::query()
            ->select('users.*')
            ->join('user_profiles as up', 'up.user_id', '=', 'users.id')
            ->join('user_privacy_settings as ups', 'ups.user_id', '=', 'users.id')
            ->join('discovery_preferences as dp', 'dp.user_id', '=', 'users.id');

        $this->applyEligibility($query, $viewer, $forRecommendations);

        return $query;
    }

    /**
     * Apply all eligibility rules to any query over `users`.
     * Kept join-free so it also works inside EXISTS subqueries.
     */
    public function applyEligibility($query, User $viewer, bool $forRecommendations = false): void
    {
        $query
            // 8. Target is never the authenticated viewer.
            ->where('users.id', '!=', $viewer->getKey())
            ->whereNull('users.deleted_at')
            // 1, 5. Active; suspended/deactivated/pending never appear.
            ->where('users.status', 'active')
            // 2. Email verified.
            ->whereNotNull('users.email_verified_at')
            // 3. Discovery explicitly enabled (canonical preference row exists AND consents).
            ->whereExists(static function (QueryBuilder $sub): void {
                $sub->selectRaw(1)
                    ->from('discovery_preferences')
                    ->whereColumn('discovery_preferences.user_id', 'users.id')
                    ->where('discovery_preferences.discovery_enabled', true);
            })
            // 4. Profile visibility permits discovery (public only until
            //    connections exist to authorize the "connections" level).
            // Phase 02 privacy settings are the source of truth here.
            ->whereExists(static function (QueryBuilder $sub): void {
                $sub->selectRaw(1)
                    ->from('user_privacy_settings')
                    ->whereColumn('user_privacy_settings.user_id', 'users.id')
                    ->where('user_privacy_settings.profile_visibility', 'public');
            });

        if ($this->enforceAdultsOnly) {
            // 10. Safety eligibility: self-reported adults only.
            $query->whereExists(static function (QueryBuilder $sub): void {
                $sub->selectRaw(1)
                    ->from('user_profiles as safety_profiles')
                    ->whereColumn('safety_profiles.user_id', 'users.id')
                    ->where(static function (QueryBuilder $age): void {
                        $age->whereNull('safety_profiles.birth_date')
                            ->orWhere('safety_profiles.birth_date', '<=', now()->subYears(18)->toDateString());
                    });
            });
        }

        // 6. Target has not blocked the viewer.
        $query->whereNotExists(static function (QueryBuilder $sub) use ($viewer): void {
            $sub->selectRaw(1)
                ->from('user_blocks')
                ->whereColumn('user_blocks.user_id', 'users.id')
                ->where('user_blocks.blocked_user_id', $viewer->getKey());
        });
        // 7. Viewer has not blocked the target.
        $query->whereNotExists(static function (QueryBuilder $sub) use ($viewer): void {
            $sub->selectRaw(1)
                ->from('user_blocks as viewer_blocks')
                ->whereColumn('viewer_blocks.blocked_user_id', 'users.id')
                ->where('viewer_blocks.user_id', $viewer->getKey());
        });
        // 9. Target is not hidden through discovery feedback.
        $query->whereNotExists(static function (QueryBuilder $sub) use ($viewer): void {
            $sub->selectRaw(1)
                ->from('discovery_feedback')
                ->whereColumn('discovery_feedback.target_user_id', 'users.id')
                ->where('discovery_feedback.user_id', $viewer->getKey());
        });

        if ($forRecommendations) {
            // 5. Recommendation consent for ranking endpoints.
            $query->whereExists(static function (QueryBuilder $sub): void {
                $sub->selectRaw(1)
                    ->from('discovery_preferences as rec_prefs')
                    ->whereColumn('rec_prefs.user_id', 'users.id')
                    ->where('rec_prefs.show_in_recommendations', true);
            });
            // Hidden interests cannot take part in explainable matching.
            $query->whereExists(static function (QueryBuilder $sub): void {
                $sub->selectRaw(1)
                    ->from('user_privacy_settings as rec_privacy')
                    ->whereColumn('rec_privacy.user_id', 'users.id')
                    ->where('rec_privacy.show_interests', true);
            });
            // A candidate must have at least one interest to produce a
            // meaningful score (see RecommendationService).
            $query->whereExists(static function (QueryBuilder $sub): void {
                $sub->selectRaw(1)
                    ->from('user_interests as candidate_interests')
                    ->whereColumn('candidate_interests.user_id', 'users.id');
            });
        }
    }

    /**
     * Stateless base query for COUNT aggregates (users table only).
     */
    public function eligibleCountQuery(User $viewer, bool $forRecommendations = false): QueryBuilder
    {
        $query = DB::table('users');
        $this->applyEligibility($query, $viewer, $forRecommendations);

        return $query;
    }

    /**
     * Whether the viewer may view the target's discovery profile.
     * Failures are indistinguishable from "profile not found": no
     * enumeration signal is returned to the caller.
     */
    public function canView(User $viewer, User $target): bool
    {
        if ($viewer->is($target)) {
            return false;
        }

        $query = DB::table('users')->where('users.id', $target->getKey());
        $this->applyEligibility($query, $viewer);

        return $query->exists();
    }

    /** Categories with at least one active interest. */
    public function activeCategories(): array
    {
        return Interest::query()->where('is_active', true)->distinct()->orderBy('category')->pluck('category')->all();
    }

    /** Categories a user may filter discovery preferences by. */
    public function validCategories(): array
    {
        return $this->activeCategories();
    }

    /**
     * Write consent through the canonical table and keep the Phase 02
     * privacy mirror in sync in one transaction, so the two can never
     * contradict each other.
     */
    public function syncConsent(User $user, bool $discoveryEnabled): void
    {
        DB::transaction(function () use ($user, $discoveryEnabled): void {
            $user->discoveryPreference()->firstOrCreate(['user_id' => $user->getKey()], ['discovery_enabled' => $discoveryEnabled]);
            $user->discoveryPreference()->update(['discovery_enabled' => $discoveryEnabled]);
            $user->privacySettings()->update(['discovery_enabled' => $discoveryEnabled]);
        });
    }

    /** Latest feedback the viewer gave a target, if any. */
    public function feedbackFor(User $viewer, User $target): ?DiscoveryFeedback
    {
        return DiscoveryFeedback::query()
            ->where('user_id', $viewer->getKey())
            ->where('target_user_id', $target->getKey())
            ->first();
    }
}
