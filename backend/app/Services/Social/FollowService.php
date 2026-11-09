<?php

namespace App\Services\Social;

use App\Exceptions\SocialConflictException;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Follow graph writes and reads. Permission and conflict checks live in
 * one place so lists, profile pages and discovery cards can never
 * disagree about what is allowed.
 */
class FollowService
{
    public function __construct(
        private readonly RelationshipService $relationships,
        private readonly SocialNotificationService $notifications,
    ) {}

    /**
     * Follow a user. Idempotent: following twice returns the current
     * state without error. Failures map to 403/409/422 JSON responses.
     *
     * @return array{0: UserFollow|null, 1: array<string, bool|string>, 2: bool} [follow, relationship, wasCreated]
     */
    public function follow(User $actor, User $target): array
    {
        $this->assertFollowAllowed($actor, $target);

        return DB::transaction(function () use ($actor, $target): array {
            // Lock the target row to serialize concurrent follows for this pair
            // so the existence check cannot race with itself (SQLite ignores locks; tests plus real drivers covered).
            DB::table('users')->where('id', $target->getKey())->lockForUpdate()->first();

            $existing = UserFollow::query()->where('follower_id', $actor->getKey())->where('following_id', $target->getKey())->first();

            if ($existing !== null) {
                return [$existing, $this->relationships->forPair($actor, $target), false];
            }

            $follow = UserFollow::query()->create(['follower_id' => $actor->getKey(), 'following_id' => $target->getKey()]);

            // afterCommit notification — never dispatched if this transaction rolls back.
            $this->notifications->newFollower($target, $actor);

            return [$follow, $this->relationships->forPair($actor, $target), true];
        });
    }

    /** Unfollow a user. Idempotent; no-op when the edge does not exist. */
    public function unfollow(User $actor, User $target): array
    {
        DB::transaction(function () use ($actor, $target): void {
            UserFollow::query()
                ->where('follower_id', $actor->getKey())
                ->where('following_id', $target->getKey())
                ->delete();
        });

        return [$this->relationships->forPair($actor, $target)];
    }

    /**
     * Paginated follower list for the target as visible to the viewer.
     * Deterministic order, search over display name and handle, per-row
     * relationship state in one batched query.
     *
     * @param  array{page?: int, per_page?: int, search?: string, sort?: string}  $params
     * @return array{data: array, meta: array{current_page: int, last_page: int, per_page: int, total: int}, relationship: array<string, bool|string>|null}
     */
    public function followers(User $viewer, User $target, array $params): array
    {
        return $this->edgeList($viewer, $target, 'followers', $params);
    }

    /** Paginated list of accounts the target follows, visible to the viewer. */
    public function following(User $viewer, User $target, array $params): array
    {
        return $this->edgeList($viewer, $target, 'following', $params);
    }

    /** Database-backed counters, never cached or fabricated. */
    public function followerCount(User $user): int
    {
        return $user->followers()->count();
    }

    public function followingCount(User $user): int
    {
        return $user->followings()->count();
    }

    /**
     * @param  array{page?: int, per_page?: int, search?: string}  $params
     * @return array{data: array, meta: array, relationship: array<string, bool|string>|null}
     */
    private function edgeList(User $viewer, User $target, string $direction, array $params): array
    {
        $targetSide = $direction === 'followers' ? 'following_id' : 'follower_id';
        $otherSide = $direction === 'followers' ? 'follower_id' : 'following_id';

        $columns = [
            'edges.id as follow_id',
            'edges.created_at as followed_at',
            'edges.follower_id',
            'edges.following_id',
            'edge_users.id as row_user_id',
            'edge_users.name',
        ];

        $query = DB::table('user_follows as edges')
            ->join('users as edge_users', 'edge_users.id', '=', 'edges.'.$otherSide)
            ->join('user_profiles as edge_profiles', 'edge_profiles.user_id', '=', 'edge_users.id')
            ->select($columns)
            ->where('edges.'.$targetSide, $target->getKey());

        $term = trim((string) ($params['search'] ?? ''));
        if ($term !== '') {
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($term)).'%';
            $query->where(static function ($sub) use ($like): void {
                $sub->whereRaw('lower(coalesce(edge_profiles.display_name, edge_users.name)) like ?', [$like])
                    ->orWhereRaw('lower(edge_profiles.username) like ?', [$like]);
            });
        }

        if (($params['sort'] ?? 'newest') === 'name') {
            $query->orderByRaw('lower(coalesce(edge_profiles.display_name, edge_users.name))')->orderBy('edge_profiles.username')->orderBy('edges.id');
        } else {
            $query->orderByDesc('edges.id')->orderBy('edges.following_id');
        }

        $page = $query->paginate($params['per_page'] ?? 10, $columns, 'page', max($params['page'] ?? 1, 1));

        // One batched hydration + one batched relationship map (no N+1).
        $rowUsers = User::query()->with(['profile', 'privacySettings'])->whereIn('users.id', collect($page->items())->pluck('row_user_id'))->get()->keyBy(static fn (User $user): int => (int) $user->getKey());
        $states = $this->relationships->forUsers($viewer, $rowUsers->values());
        $viewerId = (int) $viewer->getKey();

        $data = collect($page->items())->values()->map(static function (object $row) use ($rowUsers, $states, $viewerId): array {
            /** @var User $user */
            $user = $rowUsers[$row->row_user_id];
            $profile = $user->profile;

            return [
                'user' => [
                    'id' => $user->getKey(),
                    'username' => $profile?->username,
                    'display_name' => $profile?->display_name ?? $user->name,
                    'bio' => $profile?->bio,
                    'avatar_url' => $profile?->avatar_path ? asset('storage/'.$profile->avatar_path) : null,
                ],
                'followed_at' => $row->followed_at,
                // The viewer row carries no relationship to itself.
                'relationship' => $user->getKey() === $viewerId ? null : $states[$user->getKey()],
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
            'relationship' => $viewer->is($target) ? null : $this->relationships->forPair($viewer, $target),
        ];
    }

    /** Self, account-health, block and privacy gates for new follow edges. */
    private function assertFollowAllowed(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages(['user' => 'You cannot follow your own account.']);
        }

        if (! $target->isSociallyActive()) {
            throw ValidationException::withMessages(['user' => 'This account is not accepting follows right now.']);
        }

        if ($this->blockedBetween($actor, $target)) {
            throw new SocialConflictException('You cannot follow this account.');
        }

        $privacy = $target->privacySettings;

        if (($privacy?->profile_visibility ?? 'public') === 'private' || ! (bool) $privacy?->allow_follows) {
            throw new SocialConflictException('This profile does not accept followers.');
        }
    }

    /** Whether either side of the pair blocked the other. */
    private function blockedBetween(User $actor, User $target): bool
    {
        return DB::table('user_blocks')
            ->where(static function ($query) use ($actor, $target): void {
                $query->where(static function ($sub) use ($actor, $target): void {
                    $sub->where('user_id', $actor->getKey())->where('blocked_user_id', $target->getKey());
                })->orWhere(static function ($sub) use ($actor, $target): void {
                    $sub->where('user_id', $target->getKey())->where('blocked_user_id', $actor->getKey());
                });
            })
            ->exists();
    }
}
