<?php

namespace App\Services\Social;

use App\Exceptions\SocialConflictException;
use App\Models\User;
use App\Models\UserConnection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Accepted-connection reads and removal. Lists are paginated with
 * deterministic ordering; privacy gates live here so neither a
 * controller nor the frontend decides who may see a connection list.
 */
class ConnectionService
{
    private const PER_PAGE_DEFAULT = 10;

    public function __construct(
        private readonly RelationshipService $relationships,
        private readonly MutualConnectionService $mutuals,
    ) {}

    /**
     * The viewer's own connection list, paginated and searchable.
     *
     * @param  array{page?: int, per_page?: int, search?: string, sort?: string}  $params
     * @return array{data: array, meta: array}
     */
    public function listFor(User $owner, array $params): array
    {
        $result = $this->connectionList($owner, $params);

        return ['data' => $result['data'], 'meta' => $result['meta']];
    }

    /**
     * A user's accepted connection list as visible to the viewer. Private
     * or hidden lists disclose nothing but an empty page (no enumeration).
     *
     * @param  array{page?: int, per_page?: int, search?: string, sort?: string}  $params
     * @return array{data: array, meta: array, relationship: array<string, bool|string>|null}
     */
    public function listVisibleTo(User $viewer, User $target, array $params): array
    {
        $emptyMeta = ['current_page' => max($params['page'] ?? 1, 1), 'last_page' => 1, 'per_page' => $params['per_page'] ?? self::PER_PAGE_DEFAULT, 'total' => 0];

        if ($viewer->is($target)) {
            $own = $this->connectionList($target, $params);

            return ['data' => $own['data'], 'meta' => $own['meta'], 'relationship' => null];
        }

        $privacy = $target->privacySettings;
        $hidden = ($privacy?->profile_visibility ?? 'public') === 'private' || ! (bool) $privacy?->show_connections;

        if ($hidden) {
            return ['data' => [], 'meta' => $emptyMeta, 'relationship' => $this->relationships->forPair($viewer, $target)];
        }

        $visible = $this->connectionList($target, $params);

        return ['data' => $visible['data'], 'meta' => $visible['meta'], 'relationship' => $this->relationships->forPair($viewer, $target)];
    }

    /**
     * Remove an accepted connection. Follows are intentionally NOT
     * removed: only the connection edge disappears (product rule).
     */
    public function remove(User $actor, User $other): array
    {
        DB::transaction(function () use ($actor, $other): void {
            [$one, $two] = [min((int) $actor->getKey(), (int) $other->getKey()), max((int) $actor->getKey(), (int) $other->getKey())];

            $deleted = UserConnection::query()->where('user_one_id', $one)->where('user_two_id', $two)->delete();

            if ($deleted === 0) {
                throw new SocialConflictException('You are not connected with this person.');
            }
        });

        return [$other];
    }

    /** Database-backed accepted-connection count for one account. */
    public function countFor(User $user): int
    {
        return UserConnection::query()
            ->where(static function ($query) use ($user): void {
                $query->where('user_one_id', $user->getKey())->orWhere('user_two_id', $user->getKey());
            })
            ->count();
    }

    /** Accepted connections shared by the viewer and the target (viewer-filtered count). */
    public function mutualCount(User $viewer, User $target): int
    {
        return $this->mutuals->count($viewer, $viewer, $target);
    }

    /**
     * Paginated connection rows for one account, batched hydration and
     * per-row relationship state relative to the acting viewer.
     *
     * @param  array{page?: int, per_page?: int, search?: string, sort?: string}  $params
     * @return array{data: array, meta: array}
     */
    private function connectionList(User $owner, array $params): array
    {
        $ownerId = (int) $owner->getKey();
        $peerExpression = 'case when connections.user_one_id = '.((int) $ownerId).' then connections.user_two_id else connections.user_one_id end';

        $columns = [
            'connections.id as connection_id',
            'connections.connected_at',
            'connections.user_one_id',
            'connections.user_two_id',
            'connections.created_at',
            'peers.id as peer_id',
            'peers.name as peer_name',
            'peer_profiles.display_name',
            'peer_profiles.username as peer_username',
            'peer_profiles.bio',
            'peer_profiles.avatar_path',
        ];

        $query = DB::table('user_connections as connections')
            ->join('users as peers', 'peers.id', '=', DB::raw('('.$peerExpression.')'))
            ->join('user_profiles as peer_profiles', 'peer_profiles.user_id', '=', 'peers.id')
            ->select($columns)
            ->where(static function (QueryBuilder $query) use ($ownerId): void {
                $query->where('connections.user_one_id', $ownerId)->orWhere('connections.user_two_id', $ownerId);
            });

        $term = trim((string) ($params['search'] ?? ''));
        if ($term !== '') {
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($term)).'%';
            $query->where(static function ($sub) use ($like): void {
                $sub->whereRaw('lower(coalesce(peer_profiles.display_name, peers.name)) like ?', [$like])
                    ->orWhereRaw('lower(peer_profiles.username) like ?', [$like]);
            });
        }

        // Deterministic ordering is selected server-side so the total and
        // pagination remain correct for every sort mode.
        if (($params['sort'] ?? 'newest') === 'name') {
            $query->orderByRaw('lower(coalesce(peer_profiles.display_name, peers.name))')->orderBy('peer_profiles.username')->orderBy('connections.id');
        } else {
            $query->orderByDesc('connections.connected_at')->orderBy('connections.id');
        }

        $page = $query
            ->paginate($params['per_page'] ?? self::PER_PAGE_DEFAULT, $columns, 'page', max($params['page'] ?? 1, 1));

        // One batched hydration of peers + one batched relationship map.
        $peerUsers = User::query()->with(['profile', 'privacySettings'])->whereIn('users.id', collect($page->items())->pluck('peer_id'))->get()->keyBy(static fn (User $user): int => (int) $user->getKey());
        $states = $this->relationships->forUsers($owner, $peerUsers->values());

        $data = collect($page->items())->values()->map(static function (object $row) use ($peerUsers, $states, $ownerId): array {
            /** @var User $peer */
            $peer = $peerUsers[$row->peer_id];

            return [
                'user' => [
                    'id' => $peer->getKey(),
                    'username' => $peer->profile?->username,
                    'display_name' => $peer->profile?->display_name ?? $peer->name,
                    'bio' => $peer->profile?->bio,
                    'avatar_url' => $peer->profile?->avatar_path ? asset('storage/'.$peer->profile->avatar_path) : null,
                ],
                'connected_at' => $row->connected_at,
                // The row never carries a self relationship.
                'relationship' => (int) $peer->getKey() === $ownerId ? null : $states[$peer->getKey()],
            ];
        })->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }
}
