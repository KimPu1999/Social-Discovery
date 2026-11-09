<?php

namespace App\Services\Social;

use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes authorization-safe relationship state between a viewer and any
 * number of targets in a fixed number of batched queries (no N+1). The
 * symmetric block status ("is_blocked_by_target") is intentionally never
 * exposed; it only suppresses actions.
 */
class RelationshipService
{
    public const REQUEST_NONE = 'none';

    public const REQUEST_OUTGOING = 'outgoing_pending';

    public const REQUEST_INCOMING = 'incoming_pending';

    /**
     * Relationship state for one viewer/target pair.
     *
     * @return array<string, bool|string>
     */
    public function forPair(User $viewer, User $target): array
    {
        return $this->forUsers($viewer, collect([$target]))[(int) $target->getKey()];
    }

    /**
     * Batched relationship state for many targets in ~4 queries.
     * The targets should carry `profile` and `privacySettings` when available.
     *
     * @param  Collection<int, User>  $targets
     * @return array<int, array<string, bool|string>>
     */
    public function forUsers(User $viewer, Collection $targets): array
    {
        $ids = $targets->map(static fn (User $user) => (int) $user->getKey())->unique()->values();

        $state = [];
        foreach ($ids as $id) {
            $state[$id] = [
                'is_following' => false,
                'is_followed_by' => false,
                'is_connected' => false,
                'request_status' => self::REQUEST_NONE,
                'can_follow' => false,
                'can_connect' => false,
                'is_blocked_by_me' => false,
            ];
        }

        if ($ids->isEmpty()) {
            return $state;
        }

        $viewerId = (int) $viewer->getKey();

        // Follow edges in both directions in two indexed lookups.
        foreach (UserFollow::query()->where('follower_id', $viewerId)->whereIn('following_id', $ids)->pluck('following_id') as $id) {
            $state[(int) $id]['is_following'] = true;
        }
        foreach (UserFollow::query()->where('following_id', $viewerId)->whereIn('follower_id', $ids)->pluck('follower_id') as $id) {
            $state[(int) $id]['is_followed_by'] = true;
        }

        // Accepted connections between the viewer and each target (either side of the normalized pair).
        $connected = DB::table('user_connections')
            ->where(static function (QueryBuilder $query) use ($viewerId, $ids): void {
                $query->where(static function (QueryBuilder $sub) use ($viewerId, $ids): void {
                    $sub->where('user_one_id', $viewerId)->whereIn('user_two_id', $ids);
                })->orWhere(static function (QueryBuilder $sub) use ($viewerId, $ids): void {
                    $sub->where('user_two_id', $viewerId)->whereIn('user_one_id', $ids);
                });
            })
            ->selectRaw('case when user_one_id = ? then user_two_id else user_one_id end as peer_id', [$viewerId])
            ->get();

        foreach ($connected as $row) {
            $state[(int) $row->peer_id]['is_connected'] = true;
        }

        // Pending friend requests in either direction.
        $requests = DB::table('connection_requests')
            ->where('status', 'pending')
            ->where(static function (QueryBuilder $query) use ($viewerId, $ids): void {
                $query->where(static function (QueryBuilder $sub) use ($viewerId, $ids): void {
                    $sub->where('sender_id', $viewerId)->whereIn('receiver_id', $ids);
                })->orWhere(static function (QueryBuilder $sub) use ($viewerId, $ids): void {
                    $sub->where('receiver_id', $viewerId)->whereIn('sender_id', $ids);
                });
            })
            ->selectRaw('case when sender_id = ? then receiver_id else sender_id end as peer_id', [$viewerId])
            ->selectRaw('case when sender_id = ? then 1 else 0 end as outgoing', [$viewerId])
            ->get();

        foreach ($requests as $row) {
            $state[(int) $row->peer_id]['request_status'] = (int) $row->outgoing === 1 ? self::REQUEST_OUTGOING : self::REQUEST_INCOMING;
        }

        // Whether the viewer blocked each target. Never the reverse.
        $blocked = DB::table('user_blocks')->where('user_id', $viewerId)->whereIn('blocked_user_id', $ids)->pluck('blocked_user_id');
        foreach ($blocked as $id) {
            $state[(int) $id]['is_blocked_by_me'] = true;
        }

        foreach ($targets as $target) {
            $id = (int) $target->getKey();
            $state[$id] = $this->withPermissions($state[$id], $viewer, $target, $this->targetBlocked($viewerId, $id));
        }

        return $state;
    }

    /**
     * Layer can_follow / can_connect onto the persisted state. Permissions
     * combine account health, privacy choices, blocks and current state.
     *
     * @param  array<string, bool|string>  $state
     * @return array<string, bool|string>
     */
    private function withPermissions(array $state, User $viewer, User $target, bool $targetBlockedViewer): array
    {
        $blocked = ((bool) $state['is_blocked_by_me']) || $targetBlockedViewer;

        $privacy = $target->privacySettings;
        $profilePrivate = ($privacy?->profile_visibility ?? 'public') === 'private';

        $state['can_follow'] = $target->isSociallyActive() && ! $blocked && ! ((bool) $state['is_following'])
            && (bool) $privacy?->allow_follows && ! $profilePrivate;

        $state['can_connect'] = $target->isSociallyActive() && ! $blocked && ! ((bool) $state['is_connected'])
            && $state['request_status'] === self::REQUEST_NONE
            && (bool) $privacy?->allow_connection_requests && ! $profilePrivate;

        return $state;
    }

    /** Whether the target blocked the viewer (suppresses actions, never exposed). */
    public function targetBlocked(int $viewerId, int $targetId): bool
    {
        return DB::table('user_blocks')->where('user_id', $targetId)->where('blocked_user_id', $viewerId)->exists();
    }
}
