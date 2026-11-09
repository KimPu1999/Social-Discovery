<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Discovery\DiscoveryEligibilityService;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Support\Facades\DB;

/**
 * Phase 05 social authorization policy for user-to-user actions.
 * This is the authoritative policy for the User model; discovery
 * abilities delegate to the underlying discovery services so every
 * Phase 04 rule keeps working unchanged.
 */
#[UsePolicy(User::class)]
class SocialPolicy
{
    /** Discovery profile view (delegated to the Phase 04 eligibility service). */
    public function view(User $viewer, User $target): bool
    {
        if ($viewer->is($target) || ! $target->isSociallyActive()) {
            return false;
        }

        return app(DiscoveryEligibilityService::class)->canView($viewer, $target);
    }

    /** A viewer may not follow their own account. */
    public function follow(User $viewer, User $target): bool
    {
        if ($viewer->is($target)) {
            return false;
        }

        return $target->isSociallyActive();
    }

    /** Only the receiver may respond to a request. */
    public function respondToRequest(User $viewer, User $target): bool
    {
        return $viewer->is($target);
    }

    /** Whether the follower list of the target may be seen by the viewer. */
    public function viewFollowers(User $viewer, User $target): bool
    {
        return $this->maySeeList($viewer, $target, 'show_followers');
    }

    public function viewFollowing(User $viewer, User $target): bool
    {
        return $this->maySeeList($viewer, $target, 'show_following');
    }

    public function viewConnections(User $viewer, User $target): bool
    {
        return $this->maySeeList($viewer, $target, 'show_connections');
    }

    public function viewMutualConnections(User $viewer, User $target): bool
    {
        return $this->maySeeList($viewer, $target, 'show_mutual_connections');
    }

    /** Blocking another account (never their own). */
    public function block(User $viewer, User $target): bool
    {
        return ! $viewer->is($target);
    }

    public function unblock(User $viewer, User $target): bool
    {
        return ! $viewer->is($target);
    }

    /** Shared list gate: account health, privacy choice, plus own-list bypass. */
    private function maySeeList(User $viewer, User $target, string $setting): bool
    {
        if ($viewer->is($target)) {
            return true;
        }

        if (! $target->isSociallyActive()) {
            return false;
        }

        if ($this->blockedBetween($viewer, $target)) {
            return false;
        }

        $privacy = $target->privacySettings;

        if (($privacy?->profile_visibility ?? 'public') === 'private') {
            return false;
        }

        return (bool) $privacy?->{$setting};
    }

    /** Lists and count summaries never disclose a blocked relationship. */
    private function blockedBetween(User $viewer, User $target): bool
    {
        return DB::table('user_blocks')
            ->where(static function ($query) use ($viewer, $target): void {
                $query->where(static function ($sub) use ($viewer, $target): void {
                    $sub->where('user_id', $viewer->getKey())->where('blocked_user_id', $target->getKey());
                })->orWhere(static function ($sub) use ($viewer, $target): void {
                    $sub->where('user_id', $target->getKey())->where('blocked_user_id', $viewer->getKey());
                });
            })
            ->exists();
    }
}
