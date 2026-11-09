<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Feed reads with cursor pagination.
 *
 * Ordering rules (deterministic, `id` as the final tie-breaker):
 *  - following: posts from accounts the viewer follows, newest first
 *               (keyset cursor on published_at + id).
 *  - latest:    every eligible visible post, newest first (keyset).
 *  - for_you:   explainable relevance ranking: 3 * shared_interests(author)
 *               + 5 when the author is an accepted connection + 2 when
 *               followed. Ordered by relevance desc, published_at desc,
 *               id desc. The score is interest/connection based,
 *               time-stable, and never engagement-popularity driven.
 */
class FeedService
{
    public const TYPES = ['for_you', 'following', 'latest'];
    private const MAX_LIMIT = 30;
    private const DEFAULT_LIMIT = 15;

    /**
     * @return array{data: array<int, Post>, meta: array{type: string, next_cursor: ?string, has_more: bool}}
     */
    public function get(User $viewer, string $type, ?string $cursor, ?int $limit): array
    {
        $limit = max(1, min($limit ?? self::DEFAULT_LIMIT, self::MAX_LIMIT));

        return match ($type) {
            'following' => $this->following($viewer, $cursor, $limit),
            'for_you' => $this->forYou($viewer, $cursor, $limit),
            default => $this->latest($viewer, $cursor, $limit),
        };
    }

    /** Posts the viewer chose to hide from their own feeds (Hide Post). */
    private function hiddenPostIds(User $viewer): array
    {
        return DB::table('post_hides')
            ->where('user_id', (int) $viewer->getKey())
            ->pluck('post_id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    private function baseQuery(User $viewer): Builder
    {
        $query = Post::query()
            ->select('posts.*')
            ->with([
                'user.profile',
                'media',
                'hashtags:id,name,slug',
                'shareOf',
                'shareOf.originalPost',
                'shareOf.originalPost.user.profile',
                'shareOf.originalPost.media',
                'shareOf.originalPost.hashtags',
                'shareOf.originalPost.user:id,name,username',
            ])
            ->visibleTo($viewer)
            ->eligibleFor($viewer);

        $hidden = $this->hiddenPostIds($viewer);

        if ($hidden !== []) {
            $query->whereNotIn('posts.id', $hidden);
        }

        return $query;
    }

    /**
     * Batched metric fill (no per-row subqueries): reaction/comment/share
     * counts, viewer like/save flags, and — for "for_you" — the explainable
     * relevance parts.
     */
    private function hydrate(User $viewer, $posts, string $type): void
    {
        if ($posts->isEmpty()) {
            return;
        }

        $ids = $posts->modelKeys();
        $viewerId = (int) $viewer->getKey();

        $reactionCounts = DB::table('post_reactions')->whereIn('post_id', $ids)
            ->selectRaw('post_id, count(*) as aggregate')->groupBy('post_id')->pluck('aggregate', 'post_id');

        $commentCounts = DB::table('post_comments')->whereIn('post_id', $ids)->whereNull('deleted_at')
            ->selectRaw('post_id, count(*) as aggregate')->groupBy('post_id')->pluck('aggregate', 'post_id');

        $shareCounts = DB::table('post_shares')->whereIn('original_post_id', $ids)
            ->selectRaw('original_post_id, count(*) as aggregate')->groupBy('original_post_id')->pluck('aggregate', 'original_post_id');

        $liked = DB::table('post_reactions')->where('user_id', $viewerId)->whereIn('post_id', $ids)
            ->selectRaw('post_id, count(*) as aggregate')->groupBy('post_id')->pluck('aggregate', 'post_id');

        $saved = DB::table('saved_posts')->where('user_id', $viewerId)->whereIn('post_id', $ids)
            ->selectRaw('post_id, count(*) as aggregate')->groupBy('post_id')->pluck('aggregate', 'post_id');

        $sharedInterests = collect();
        $connectedIds = collect();
        $followedIds = collect();

        if ($type === 'for_you') {
            $authorIds = $posts->pluck('user_id')->unique()->values();

            $sharedInterests = DB::table('user_interests as mine')
                ->join('user_interests as theirs', 'theirs.interest_id', '=', 'mine.interest_id')
                ->where('mine.user_id', $viewerId)
                ->whereIn('theirs.user_id', $authorIds)
                ->whereNotIn('theirs.user_id', [(string) $viewerId])
                ->selectRaw('theirs.user_id as user_id, count(*) as shared')
                ->groupBy('theirs.user_id')
                ->pluck('shared', 'user_id');

            $connectedIds = DB::table('user_connections')
                ->where('user_one_id', $viewerId)
                ->orWhere('user_two_id', $viewerId)
                ->get()
                ->map(static fn ($row) => (int) ($row->user_one_id === $viewerId ? $row->user_two_id : $row->user_one_id));

            $followedIds = DB::table('user_follows')
                ->where('follower_id', $viewerId)
                ->pluck('following_id');
        }

        foreach ($posts as $post) {
            $postId = $post->getKey();
            $post->user_id = (int) $post->user_id;

            $shared = (int) ($sharedInterests[$post->user_id] ?? 0);
            $connected = $connectedIds->contains($post->user_id);
            $followed = $followedIds->contains($post->user_id);

            $post->forceFill([
                'reaction_count' => (int) ($reactionCounts[$postId] ?? 0),
                'comment_count' => (int) ($commentCounts[$postId] ?? 0),
                'share_count' => (int) ($shareCounts[$postId] ?? 0),
            ])->syncOriginal();

            $post->setAttribute('viewer_liked', ($liked[$postId] ?? 0) > 0);
            $post->setAttribute('viewer_saved', ($saved[$postId] ?? 0) > 0);

            if ($type === 'for_you') {
                $post->relevance = 3 * $shared + ($connected ? 5 : 0) + ($followed ? 2 : 0);
                $post->relevance_parts = [
                    'shared_interests' => $shared,
                    'connection' => $connected,
                    'following' => $followed,
                ];
            }
        }
    }

    private function latest(User $viewer, ?string $cursor, int $limit): array
    {
        $query = $this->baseQuery($viewer);

        $this->applyKeyset($query, $cursor);

        return $this->page(
            $query->orderByDesc('posts.published_at')->orderByDesc('posts.id')->limit($limit + 1)->get(),
            $viewer,
            $limit,
            'latest',
            fn (Post $post): string => $this->encode(['a' => (string) $post->published_at->format('Y-m-d H:i:s'), 'i' => (int) $post->getKey()]),
        );
    }

    private function following(User $viewer, ?string $cursor, int $limit): array
    {
        $followedIds = DB::table('user_follows')
            ->where('follower_id', (int) $viewer->getKey())
            ->pluck('following_id')
            ->map(static fn ($id) => (int) $id);

        if ($followedIds->isEmpty()) {
            return ['data' => [], 'meta' => ['type' => 'following', 'next_cursor' => null, 'has_more' => false]];
        }

        $query = $this->baseQuery($viewer)->whereIn('posts.user_id', $followedIds->all());

        $this->applyKeyset($query, $cursor);

        return $this->page(
            $query->orderByDesc('posts.published_at')->orderByDesc('posts.id')->limit($limit + 1)->get(),
            $viewer,
            $limit,
            'following',
            fn (Post $post): string => $this->encode(['a' => (string) $post->published_at->format('Y-m-d H:i:s'), 'i' => (int) $post->getKey()]),
        );
    }

    /** Own posts are excluded: For You surfaces other people's content. */
    private function forYou(User $viewer, ?string $cursor, int $limit): array
    {
        $offset = $this->decodeOffset($cursor);

        $found = $this->baseQuery($viewer)
            ->where('posts.user_id', '!=', (int) $viewer->getKey())
            ->orderByRaw($this->relevanceExpression($viewer).' desc')
            ->orderByDesc('posts.published_at')
            ->orderByDesc('posts.id')
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $page = $this->page($found, $viewer, $limit, 'for_you', null);

        if ($page['meta']['has_more']) {
            $page['meta']['next_cursor'] = $this->encode(['o' => $offset + $limit]);
        }

        return $page;
    }

    /** Time-stable, explainable relevance expression (integer-only inputs). */
    private function relevanceExpression(User $viewer): string
    {
        $viewerId = (int) $viewer->getKey();

        return '(3 * (select count(*) from user_interests pa
                    join user_interests pb on pa.interest_id = pb.interest_id
                    where pa.user_id = '.$viewerId.' and pb.user_id = posts.user_id)'
            .' + case when exists (select 1 from user_connections fc'
                    .' where (fc.user_one_id = '.$viewerId.' and fc.user_two_id = posts.user_id)'
                    .'    or (fc.user_two_id = '.$viewerId.' and fc.user_one_id = posts.user_id)) then 5 else 0 end'
            .' + case when exists (select 1 from user_follows ff'
                    .' where ff.follower_id = '.$viewerId.' and ff.following_id = posts.user_id) then 2 else 0 end)';
    }

    /**
     * Slice one page, hydrate batched metrics and build cursor metadata.
     *
     * @param  (\Closure(Post): string)|null  $keysetMarker  null when offset cursors handle continuation
     * @return array{data: array<int, Post>, meta: array{type: string, next_cursor: ?string, has_more: bool}}
     */
    private function page($found, User $viewer, int $limit, string $type, $keysetMarker): array
    {
        $hasMore = $found->count() > $limit;
        $page = $found->take($limit)->values();

        $this->hydrate($viewer, $page, $type);

        return [
            'data' => $page->all(),
            'meta' => [
                'type' => $type,
                'next_cursor' => $hasMore && $keysetMarker !== null ? $keysetMarker($page->last()) : null,
                'has_more' => $hasMore,
            ],
        ];
    }

    /** Strict descending keyset: (published_at, id) < (cursor Accredited, cursor id). */
    private function applyKeyset(Builder $query, ?string $cursor): void
    {
        $decoded = $cursor !== null ? $this->decode($cursor) : null;

        if ($decoded === null || ! isset($decoded['a'], $decoded['i'])) {
            return;
        }

        $anchor = (string) $decoded['a'];
        $lastId = (int) $decoded['i'];

        $query->where(static function ($outer) use ($anchor, $lastId): void {
            $outer->where('posts.published_at', '<', $anchor)
                ->orWhere(static function ($tie) use ($anchor, $lastId): void {
                    $tie->where('posts.published_at', '=', $anchor)
                        ->where('posts.id', '<', $lastId);
                });
        });
    }

    private function decodeOffset(?string $cursor): int
    {
        $decoded = $cursor !== null ? $this->decode($cursor) : null;

        return max(0, (int) ($decoded['o'] ?? 0));
    }

    /** @return array<string, mixed>|null */
    private function decode(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $json = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true);

        return is_array($json) ? $json : null;
    }

    /** Opaque, URL-safe cursor token. */
    public static function encode(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    }
}
