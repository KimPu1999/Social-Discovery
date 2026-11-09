<?php

namespace App\Policies;

use App\Models\PostComment;
use App\Models\User;
use App\Services\Feed\PostAccessService;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

#[UsePolicy(PostComment::class)]
class PostCommentPolicy
{
    public function __construct(private readonly PostAccessService $access) {}

    /** Seeing a comment equals seeing its post. */
    public function view(User $viewer, PostComment $comment): bool
    {
        return $this->access->canViewComment($viewer, $comment);
    }

    public function update(User $viewer, PostComment $comment): bool
    {
        return $viewer->is($comment->user) && $comment->deleted_at === null;
    }

    public function delete(User $viewer, PostComment $comment): bool
    {
        return $viewer->is($comment->user) && $comment->deleted_at === null;
    }

    public function reply(User $viewer, PostComment $comment): bool
    {
        return $this->access->canComment($viewer, $comment->post)
            && $comment->deleted_at === null
            && $comment->parent_id === null;
    }
}
