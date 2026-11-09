<?php

namespace App\Http\Resources\Feed;

use App\Models\PostComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PostComment $comment */
        $comment = $this->resource;

        return [
            'id' => (int) $comment->getKey(),
            'post_id' => (int) $comment->post_id,
            'parent_id' => $comment->parent_id !== null ? (int) $comment->parent_id : null,
            'body' => $comment->body,
            'edited_at' => $comment->edited_at,
            'created_at' => $comment->created_at,
            'author' => AuthorResource::make($comment->user),
            'is_authored_by_viewer' => $request->user()?->is($comment->user) ?? false,
            'replies' => self::collection(
                $comment->relationLoaded('replies') ? $comment->replies : collect(),
            ),
        ];
    }
}
