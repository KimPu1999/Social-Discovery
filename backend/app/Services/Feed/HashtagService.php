<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\PostHashtag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Hashtag extraction, slug normalization, trending list and tag feeds. */
class HashtagService
{
    /**
     * Extract `#tag` tokens from a post body and resync the normalized tags.
     *
     * @param  Post  $post
     * @return array<int, int> attached hashtag ids
     */
    public function syncForPost(Post $post): array
    {
        $text = (string) $post->body;

        if (trim($text) === '') {
            $post->hashtags()->sync([]);

            return [];
        }

        // Unicode-aware: letters/numbers/underscore after '#', de-duplicated case-insensitively.
        preg_match_all('/#([\p{L}\p{N}_]{1,80})/u', $text, $matches);

        $ids = collect($matches[1] ?? [])
            ->map(static fn (string $token): string => trim($token, '_'))
            ->filter(static fn (string $token): bool => mb_strlen($token) >= 2)
            ->unique(static fn (string $token): string => mb_strtolower($token))
            ->map(static function (string $token): int {
                $slug = \Illuminate\Support\Str::slug($token, '-', 'en') ?: 'tag-'.mb_strtolower($token);

                return (int) PostHashtag::query()
                    ->firstOrCreate(['slug' => $slug], ['name' => $token])
                    ->getKey();
            })
            ->values()
            ->all();

        $post->hashtags()->sync($ids);

        return $ids;
    }

    /** Hashtags visible in the tag directory (published posts only). */
    public function trending(string $search = '', int $perPage = 20): LengthAwarePaginator
    {
        return PostHashtag::query()
            ->when($search !== '', static function ($query) use ($search): void {
                $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($search)).'%';
                $query->whereRaw('lower(name) like ?', [$like]);
            })
            ->withCount(['posts' => static function ($query): void {
                $query->where('posts.status', 'published')->whereNotNull('posts.published_at');
            }])
            ->orderByDesc('posts_count')
            ->orderBy('slug')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** Published posts carrying `hashtag`, restricted to the viewer's visibility. */
    public function postsForHashtag(?User $viewer, PostHashtag $hashtag, int $perPage = 10): LengthAwarePaginator
    {
        $query = Post::query()
            ->with(['user.profile', 'media', 'sharedFrom', 'hashtags'])
            ->visibleTo($viewer)
            ->eligibleFor($viewer)
            ->whereHas('hashtags', static function ($query) use ($hashtag): void {
                $query->where('post_hashtags.id', $hashtag->getKey());
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        return $query->paginate($perPage);
    }
}
