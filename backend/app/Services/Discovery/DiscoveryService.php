<?php

namespace App\Services\Discovery;

use App\Models\DiscoveryPreference;
use App\Models\Interest;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query, filtering, pagination and read-model assembly for discovery.
 * Eligibility and scoring live in the dedicated services; this class
 * composes them into API-ready results.
 */
class DiscoveryService
{
    public function __construct(
        private readonly DiscoveryEligibilityService $eligibility,
        private readonly RecommendationService $recommender,
    ) {}

    /**
     * Paginated, filterable, sortable exploration of eligible people.
     * Only one page of candidates is ever hydrated (bounded retrieval).
     *
     * @param  array{search?: string, interest_ids?: array<int, int>, sort?: string, per_page?: int, page?: int}  $filters
     */
    public function people(User $viewer, array $filters): LengthAwarePaginator
    {
        $viewerInterestIds = RecommendationService::viewerInterestIds($viewer);

        $query = $this->eligibility->eligibleQuery($viewer)->with('profile');

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $containsLike = '%'.strtr(mb_strtolower($term), ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']).'%';
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($term)).'%';
            $query->where(static function ($sub) use ($like, $containsLike): void {
                $sub->whereRaw('lower(up.display_name) like ?', [$like])
                    ->orWhereRaw('lower(up.username) like ?', [$like])
                    ->orWhereRaw("lower(coalesce(up.bio, '')) like ?", [$containsLike])
                    ->orWhereHas('interests', static function ($interestQuery) use ($containsLike): void {
                        $interestQuery->where('is_active', true)->whereRaw('lower(name) like ?', [$containsLike]);
                    });
            });
        }

        if (! empty($filters['interest_ids'])) {
            $query->whereHas('interests', static function ($sub) use ($filters): void {
                $sub->whereKey($filters['interest_ids']);
            });
        }

        match ($filters['sort'] ?? 'username') {
            'relevance' => $viewerInterestIds === []
                ? $query->orderBy('up.username')
                : $query->orderByRaw($this->recommender->sharedCountExpression($viewer).' desc')->orderBy('up.username'),
            'recently_joined' => $query->orderByDesc('users.created_at'),
            'username_desc' => $query->orderByDesc('up.username'),
            default => $query->orderBy('up.username'),
        };
        // Stable deterministic tie-breaker for every sort.
        $query->orderBy('users.id');

        $page = $query->paginate($filters['per_page'] ?? 10, ['users.*'], 'page', max($filters['page'] ?? 1, 1));

        $this->attachMatchSummary($page->getCollection(), $viewer, $viewerInterestIds);

        return $page;
    }

    /**
     * Eligible people who are not already connected and do not have an
     * outstanding request in either direction. This is intentionally a
     * separate query from the general explorer so the Connections page can
     * promise actionable suggestions without filtering after pagination.
     *
     * @param  array{search?: string, sort?: string, per_page?: int, page?: int}  $filters
     */
    public function suggestions(User $viewer, array $filters): LengthAwarePaginator
    {
        $viewerInterestIds = RecommendationService::viewerInterestIds($viewer);
        $query = $this->eligibility->eligibleQuery($viewer)->with('profile');

        $query->whereNotExists(static function (QueryBuilder $sub) use ($viewer): void {
            $sub->selectRaw(1)->from('user_connections as suggestion_connections')
                ->where(static function (QueryBuilder $pair) use ($viewer): void {
                    $pair->where(static function (QueryBuilder $forward) use ($viewer): void {
                        $forward->where('suggestion_connections.user_one_id', $viewer->getKey())
                            ->whereColumn('suggestion_connections.user_two_id', 'users.id');
                    })->orWhere(static function (QueryBuilder $reverse) use ($viewer): void {
                        $reverse->whereColumn('suggestion_connections.user_one_id', 'users.id')
                            ->where('suggestion_connections.user_two_id', $viewer->getKey());
                    });
                });
        });

        $query->whereNotExists(static function (QueryBuilder $sub) use ($viewer): void {
            $sub->selectRaw(1)->from('connection_requests as suggestion_requests')
                ->where('suggestion_requests.status', 'pending')
                ->where(static function (QueryBuilder $pair) use ($viewer): void {
                    $pair->where(static function (QueryBuilder $forward) use ($viewer): void {
                        $forward->where('suggestion_requests.sender_id', $viewer->getKey())
                            ->whereColumn('suggestion_requests.receiver_id', 'users.id');
                    })->orWhere(static function (QueryBuilder $reverse) use ($viewer): void {
                        $reverse->whereColumn('suggestion_requests.sender_id', 'users.id')
                            ->where('suggestion_requests.receiver_id', $viewer->getKey());
                    });
                });
        });

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($term)).'%';
            $containsLike = '%'.strtr(mb_strtolower($term), ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']).'%';
            $query->where(static function ($sub) use ($like, $containsLike): void {
                $sub->whereRaw('lower(up.display_name) like ?', [$like])
                    ->orWhereRaw('lower(up.username) like ?', [$like])
                    ->orWhereRaw("lower(coalesce(up.bio, '')) like ?", [$containsLike]);
            });
        }

        if (($filters['sort'] ?? 'newest') === 'name') {
            $query->orderBy('up.display_name')->orderBy('up.username');
        } else {
            $query->orderByDesc('users.created_at');
        }
        $query->orderBy('users.id');

        $page = $query->paginate($filters['per_page'] ?? 10, ['users.*'], 'page', max($filters['page'] ?? 1, 1));
        $this->attachMatchSummary($page->getCollection(), $viewer, $viewerInterestIds);

        return $page;
    }

    /**
     * Discover overview: consent state, categories and real counts only —
     * nothing here is fabricated.
     */
    public function overview(User $viewer): array
    {
        $preference = $this->preferencesFor($viewer);
        $discoverableCount = $this->eligibility->eligibleCountQuery($viewer)->count();
        $recommendedCount = RecommendationService::viewerInterestIds($viewer) === []
            ? 0
            : $this->eligibility->eligibleCountQuery($viewer, forRecommendations: true)
                ->whereRaw($this->recommender->sharedCountExpression($viewer).' > 0')
                ->count();

        $categories = Interest::query()
            ->where('is_active', true)
            ->groupBy('category')
            ->orderBy('category')
            ->get([DB::raw('category'), DB::raw('count(*) as interests_count')]);

        return [
            'discovery_enabled' => (bool) $preference->discovery_enabled,
            'show_in_recommendations' => (bool) $preference->show_in_recommendations,
            'counts' => [
                'discoverable_people' => $discoverableCount,
                'recommended_people' => $recommendedCount,
                'active_interests' => Interest::query()->where('is_active', true)->count(),
                'my_interests' => $viewer->interests()->count(),
                'hidden_profiles' => $viewer->discoveryFeedback()->count(),
            ],
            'categories' => $categories,
        ];
    }

    /**
     * Active interests with real discoverable-user counts per interest,
     * grouped by category. One aggregate query over user_interests with
     * eligibility expressed as EXISTS — no N+1.
     */
    public function interestsWithCounts(User $viewer): Collection
    {
        $counts = DB::table('user_interests as ui')
            ->select('ui.interest_id', DB::raw('count(distinct ui.user_id) as people_count'))
            ->whereExists($this->eligibleExistsClosure($viewer))
            ->groupBy('ui.interest_id')
            ->pluck('people_count', 'interest_id');

        return Interest::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(function (Interest $interest) use ($counts): Interest {
                $interest->people_count = (int) ($counts[$interest->getKey()] ?? 0);

                return $interest;
            })
            ->groupBy('category');
    }

    /**
     * Privacy-safe public profile preview. Returns null when the profile
     * is unavailable for any eligible-reason (missing, private, suspended, unverified,
     * age-gated, blocked either way, hidden through feedback) — callers
     * must answer with one uniform response so discovery cannot be used
     * as an account enumeration oracle.
     */
    public function viewableProfile(User $viewer, string $username): ?UserProfile
    {
        $target = User::query()
            ->with(['profile', 'privacySettings', 'discoveryPreference'])
            ->whereHas('profile', static function ($query) use ($username): void {
                $query->where('username', mb_strtolower($username));
            })
            ->first();

        if (! $target || $target->is($viewer) || ! $this->eligibility->canView($viewer, $target)) {
            return null;
        }

        $this->attachMatchSummary(collect([$target]), $viewer, RecommendationService::viewerInterestIds($viewer));

        // Impression for retention-bounded recommendation-quality tracking.
        DB::table('discovery_impressions')->insert([
            'viewer_id' => $viewer->getKey(),
            'target_user_id' => $target->getKey(),
            'viewed_at' => now(),
        ]);

        // Keep the fully annotated target user attached to the returned
        // profile. The controller/resource needs the same instance for the
        // relationship state, match summary and privacy-filtered interests;
        // lazy-loading profile->user here would drop those attributes.
        $profile = $target->profile;
        $profile->setRelation('user', $target);

        return $profile;
    }

    /**
     * Attach `discovery_interests` (target interests that the viewer does
     * not share) and `match_summary` to each user in one batched query.
     * Targets keeping their interests private get neither.
     */
    public function attachMatchSummary(Collection $users, User $viewer, array $viewerInterestIds): Collection
    {
        if ($users->isEmpty()) {
            return $users;
        }

        // This method is also used for a single profile preview, where the
        // caller passes a base Support collection rather than an Eloquent
        // collection. Normalize ids without relying on modelKeys().
        $userIds = $users->map(static fn (User $user): int => (int) $user->getKey())->all();
        $annotated = $this->recommender->annotatedInterests($userIds, $viewerInterestIds);
        $privateInterests = DB::table('user_privacy_settings')
            ->whereIn('user_id', $userIds)
            ->where('show_interests', false)
            ->pluck('user_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        foreach ($users as $user) {
            $userId = (int) $user->getKey();
            $interests = collect($annotated[$userId] ?? []);

            if (in_array($userId, $privateInterests, true)) {
                $user->discovery_interests = collect();
                $user->match_summary = ['shared' => null];

                continue;
            }

            $shared = $interests->filter(static fn (object $interest): bool => $interest->viewer_selected)->values();
            $user->discovery_interests = $interests->values();

            if (count($viewerInterestIds) === 0 || $shared->isEmpty()) {
                // No signal available: no misleading percentage.
                $user->match_summary = ['shared' => null];

                continue;
            }

            $user->match_summary = ['shared' => [
                'score' => round($shared->count() / (count($viewerInterestIds) + $interests->count() - $shared->count()) * 100, 1),
                'shared_count' => $shared->count(),
                'shared_interests' => $shared->values(),
            ]];
        }

        return $users;
    }

    public function preferencesFor(User $user): DiscoveryPreference
    {
        return $user->discoveryPreference()->firstOrNew(['user_id' => $user->getKey()]);
    }

    /** WHERE EXISTS closure mirroring eligibility, keyed on ui.user_id. */
    private function eligibleExistsClosure(User $viewer): \Closure
    {
        return function ($sub) use ($viewer): void {
            $sub->selectRaw(1)->from('users')->whereColumn('users.id', 'ui.user_id');
            $this->eligibility->applyEligibility($sub, $viewer);
        };
    }
}
