<?php

namespace App\Services\Social;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Blocking lifecycle. One block transaction tears apart every active
 * relationship in both directions: follows, the accepted connection and
 * all outstanding pending connection requests. Unblocking never restores
 * anything. Notifications are never sent to the blocked user.
 */
class BlockService
{
    /** Pair-level destructive block with all relationship teardown. */
    public function block(User $actor, User $target): array
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages(['user' => 'You cannot block your own account.']);
        }

        return DB::transaction(function () use ($actor, $target): array {
            // Serialize block state for this pair against concurrent actions.
            DB::table('users')->whereIn('id', [$actor->getKey(), $target->getKey()])->orderBy('id')->lockForUpdate()->get();

            // Idempotent: blocking twice keeps a single row.
            DB::table('user_blocks')->updateOrInsert(
                ['user_id' => $actor->getKey(), 'blocked_user_id' => $target->getKey()],
                ['updated_at' => now(), 'created_at' => now()],
            );

            $actorId = (int) $actor->getKey();
            $targetId = (int) $target->getKey();

            // Follows stop in both directions.
            DB::table('user_follows')
                ->where(static function ($query) use ($actorId, $targetId): void {
                    $query->where(static function ($sub) use ($actorId, $targetId): void {
                        $sub->where('follower_id', $actorId)->where('following_id', $targetId);
                    })->orWhere(static function ($sub) use ($actorId, $targetId): void {
                        $sub->where('follower_id', $targetId)->where('following_id', $actorId);
                    });
                })
                ->delete();

            // The accepted connection disappears.
            DB::table('user_connections')
                ->where('user_one_id', min($actorId, $targetId))
                ->where('user_two_id', max($actorId, $targetId))
                ->delete();

            // Outstanding connection requests between them become cancelled.
            DB::table('connection_requests')
                ->where('status', 'pending')
                ->where(static function ($query) use ($actorId, $targetId): void {
                    $query->where(static function ($sub) use ($actorId, $targetId): void {
                        $sub->where('sender_id', $actorId)->where('receiver_id', $targetId);
                    })->orWhere(static function ($sub) use ($actorId, $targetId): void {
                        $sub->where('sender_id', $targetId)->where('receiver_id', $actorId);
                    });
                })
                ->update(['status' => 'cancelled', 'responded_at' => now(), 'updated_at' => now()]);

            return ['blocked' => true];
        });
    }

    /** Remove the block edge. Follows and connections are NOT restored. */
    public function unblock(User $actor, User $target): array
    {
        DB::transaction(function () use ($actor, $target): void {
            DB::table('user_blocks')
                ->where('user_id', $actor->getKey())
                ->where('blocked_user_id', $target->getKey())
                ->delete();
        });

        return ['blocked' => false];
    }

    /** Has either side of this pair blocked the other? */
    public function blockedBetween(User $first, User $second): bool
    {
        return DB::table('user_blocks')
            ->where(static function ($query) use ($first, $second): void {
                $query->where(static function ($sub) use ($first, $second): void {
                    $sub->where('user_id', $first->getKey())->where('blocked_user_id', $second->getKey());
                })->orWhere(static function ($sub) use ($first, $second): void {
                    $sub->where('user_id', $second->getKey())->where('blocked_user_id', $first->getKey());
                });
            })
            ->exists();
    }

    /** Does the actor block the target? */
    public function blocks(User $actor, User $target): bool
    {
        return DB::table('user_blocks')
            ->where('user_id', $actor->getKey())
            ->where('blocked_user_id', $target->getKey())
            ->exists();
    }

    /**
     * Paginated blocked account list for the actor, searchable.
     *
     * @param  array{page?: int, per_page?: int, search?: string, sort?: string}  $params
     * @return array{data: array, meta: array}
     */
    public function blockedUsers(User $actor, array $params): array
    {
        $actorId = (int) $actor->getKey();

        $columns = ['blocked_users.id as blocked_id', 'blocks.blocked_user_id', 'blocks.created_at as blocked_at', 'blocked_profiles.display_name', 'blocked_profiles.username as blocked_username', 'blocked_profiles.bio', 'blocked_profiles.avatar_path', 'blocked_users.name as blocked_fallback'];

        $query = DB::table('user_blocks as blocks')
            ->join('users as blocked_users', 'blocked_users.id', '=', 'blocks.blocked_user_id')
            ->leftJoin('user_profiles as blocked_profiles', 'blocked_profiles.user_id', '=', 'blocked_users.id')
            ->select($columns)
            ->where('blocks.user_id', $actorId);

        $term = trim((string) ($params['search'] ?? ''));
        if ($term !== '') {
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($term)).'%';
            $query->where(static function ($sub) use ($like): void {
                $sub->whereRaw('lower(coalesce(blocked_profiles.display_name, blocked_users.name)) like ?', [$like])
                    ->orWhereRaw('lower(blocked_profiles.username) like ?', [$like]);
            });
        }

        if (($params['sort'] ?? 'newest') === 'name') {
            $query->orderByRaw('lower(coalesce(blocked_profiles.display_name, blocked_users.name))')->orderBy('blocked_profiles.username')->orderBy('blocks.blocked_user_id');
        } else {
            $query->orderByDesc('blocks.created_at')->orderBy('blocks.blocked_user_id');
        }

        $page = $query->paginate($params['per_page'] ?? 10, $columns, 'page', max($params['page'] ?? 1, 1));

        $data = collect($page->items())->values()->map(static fn (object $row): array => [
            'user' => [
                'id' => (int) $row->blocked_user_id,
                'username' => $row->blocked_username,
                'display_name' => $row->display_name ?? $row->blocked_fallback,
                'bio' => $row->bio,
                'avatar_url' => $row->avatar_path ? asset('storage/'.$row->avatar_path) : null,
            ],
            'blocked_at' => $row->blocked_at,
        ])->all();

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
