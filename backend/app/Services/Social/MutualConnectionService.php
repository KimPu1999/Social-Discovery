<?php

namespace App\Services\Social;

use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mutual (shared) connections computed fully in SQL with indexed scans —
 * whole connection lists are never hydrated. Everything the viewer sees
 * is privacy-filtered: private accounts, and accounts blocked in either
 * direction between the viewer and the peer, never appear.
 */
class MutualConnectionService
{
    /** Largest shared set scanned; retrieval stays bounded. */
    public const MAX_SHARED_SCAN = 500;

    /**
     * Number of shared connections between two users, filtered to the
     * peers the viewer is allowed to see.
     */
    public function count(User $viewer, User $first, User $second): int
    {
        return $this->sharedConnections($viewer, $first, $second, 0)['count'];
    }

    /**
     * Shared-connection summary between two users as visible to the
     * viewer: filtered count plus a bounded, privacy-safe preview.
     *
     * @return array{count: int, data: array<int, array{id: int, username: string|null, display_name: string, avatar_url: string|null}>}
     */
    public function sharedConnections(User $viewer, User $first, User $second, int $limit = 6): array
    {
        [$firstId, $secondId] = [(int) $first->getKey(), (int) $second->getKey()];
        $viewerId = (int) $viewer->getKey();

        $count = 0;
        $preview = [];

        if ($firstId !== $secondId) {
            $peerIds = DB::table('user_connections')
                ->where(static function (QueryBuilder $query) use ($firstId): void {
                    $query->where('user_one_id', $firstId)->orWhere('user_two_id', $firstId);
                })
                ->selectRaw('case when user_one_id = ? then user_two_id else user_one_id end as peer_id', [$firstId])
                ->where('user_one_id', '!=', 'user_two_id')
                ->limit(self::MAX_SHARED_SCAN)
                ->pluck('peer_id')
                ->map(static fn ($id): int => (int) $id)
                ->reject(static fn (int $id): bool => in_array($id, [$viewerId, $firstId, $secondId], true))
                ->unique()
                ->values();

            $peerIds = $this->onlySharedWith($peerIds, $secondId);

            if ($peerIds->isNotEmpty()) {
                $visible = $this->visiblePeers($viewerId, $peerIds);
                $count = $visible->count();

                if ($limit > 0) {
                    $preview = $visible->take($limit)->values()->map(static fn (User $user): array => [
                        'id' => (int) $user->getKey(),
                        'username' => $user->profile?->username,
                        'display_name' => $user->profile?->display_name ?? $user->name,
                        'avatar_url' => $user->profile?->avatar_path ? asset('storage/'.$user->profile->avatar_path) : null,
                    ])->all();
                }
            }
        }

        return ['count' => $count, 'data' => $preview];
    }

    /** Compact summary used by profile and card resources. */
    public function summary(User $viewer, User $first, User $second): array
    {
        $result = $this->sharedConnections($viewer, $first, $second, 5);

        return ['count' => $result['count'], 'preview' => $result['data']];
    }

    /**
     * Keep only peers who are also connected to `$secondId`, via an
     * EXISTS probe over the normalized pair index.
     *
     * @param  Collection<int, int>  $peerIds
     * @return Collection<int, int>
     */
    private function onlySharedWith(Collection $peerIds, int $secondId): Collection
    {
        $shared = DB::table('user_connections')
            ->where(static function (QueryBuilder $query) use ($secondId, $peerIds): void {
                $query->where(static function (QueryBuilder $sub) use ($secondId, $peerIds): void {
                    $sub->where('user_one_id', $secondId)->whereIn('user_two_id', $peerIds->all());
                })->orWhere(static function (QueryBuilder $sub) use ($secondId, $peerIds): void {
                    $sub->where('user_two_id', $secondId)->whereIn('user_one_id', $peerIds->all());
                });
            })
            ->selectRaw('case when user_one_id = ? then user_two_id else user_one_id end as peer_id', [$secondId])
            ->pluck('peer_id')
            ->map(static fn ($id): int => (int) $id)
            ->flip();

        return $peerIds->filter(static fn (int $id): bool => $shared->has($id))->values();
    }

    /**
     * Hydrate candidate peers once and apply both privacy levels:
     * 1) the peer's own visibility choice, 2) blocking between the viewer
     * and the peer (either direction), 3) suspended or deleted accounts.
     *
     * @param  Collection<int, int>  $peerIds
     */
    private function visiblePeers(int $viewerId, Collection $peerIds): Collection
    {
        $users = User::query()
            ->with(['profile', 'privacySettings'])
            ->whereIn('users.id', $peerIds->all())
            ->where('users.status', 'active')
            ->get()
            ->filter(static function (User $user): bool {
                // The peer's own visibility choice governs exposure in shared lists.
                return ($user->privacySettings?->profile_visibility ?? 'public') !== 'private';
            });

        // Blocking in either direction between the viewer and each peer.
        $blocked = DB::table('user_blocks')
            ->where(static function (QueryBuilder $query) use ($viewerId, $users): void {
                $query->where(static function (QueryBuilder $sub) use ($viewerId, $users): void {
                    $sub->where('user_id', $viewerId)->whereIn('blocked_user_id', $users->modelKeys());
                })->orWhere(static function (QueryBuilder $sub) use ($viewerId, $users): void {
                    $sub->whereIn('user_id', $users->modelKeys())->where('blocked_user_id', $viewerId);
                });
            })
            ->get()
            ->flatMap(static fn (object $row): array => [(int) $row->user_id === $viewerId ? (int) $row->blocked_user_id : (int) $row->user_id])
            ->unique()
            ->all();

        return $users->reject(static fn (User $user): bool => in_array((int) $user->getKey(), $blocked, true))
            ->sortBy(static fn (User $user) => [$user->profile?->username ?? '', (int) $user->getKey()])
            ->values();
    }
}
