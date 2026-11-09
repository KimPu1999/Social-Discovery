<?php

namespace App\Http\Resources\Feed;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One feed/post card payload. Private metadata (draft state, removed
 * status, edit trails) is only exposed to the post's author.
 */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource;
        $viewer = $request->user();
        $isAuthor = $viewer !== null && $post->user_id === $viewer->getKey();

        $shareAttribution = null;
        if ((! $post->relationLoaded('shareOf') || $post->shareOf !== null) && $post->relationLoaded('shareOf')) {
            $shareOf = $post->shareOf;
            $original = $shareOf?->relationLoaded('originalPost') ? $shareOf->originalPost : null;

            // Only attribute the source when the viewer may see it.
            if ($original !== null && $original->deleted_at === null && $original->status === 'published') {
                $shareAttribution = [
                    'post_id' => (int) $original->getKey(),
                    'author' => AuthorResource::make($original->user),
                ];
            }
        }

        return [
            'id' => (int) $post->getKey(),
            'body' => $post->body,
            'visibility' => $post->visibility,
            'status' => $isAuthor ? $post->status : 'published',
            'is_authored_by_viewer' => $isAuthor,
            'published_at' => $post->published_at,
            'edited_at' => $post->edited_at,
            'created_at' => $post->created_at,
            'author' => AuthorResource::make($post->user),
            'media' => PostMediaResource::collection($post->media),
            'hashtags' => $post->hashtags->map(static fn ($tag) => ['name' => $tag->name, 'slug' => $tag->slug])->values(),
            'counts' => [
                'reactions' => (int) ($post->getAttribute('reaction_count') ?? $post->reactions()->count()),
                'comments' => (int) ($post->getAttribute('comment_count') ?? $post->comments()->count()),
                'shares' => (int) ($post->getAttribute('share_count') ?? $post->shares()->count()),
            ],
            'viewer' => [
                'liked' => (bool) $post->getAttribute('viewer_liked'),
                'saved' => (bool) $post->getAttribute('viewer_saved'),
            ],
            'relevance' => $post->getAttribute('relevance'),
            'relevance_parts' => $post->getAttribute('relevance_parts'),
            'shared_from' => $shareAttribution,
            'share_url' => $shareAttribution === null ? null : url('/posts/'.$post->getKey()),
        ];
    }
}
