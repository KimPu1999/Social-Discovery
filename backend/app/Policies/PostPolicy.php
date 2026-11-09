<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Services\Feed\PostAccessService;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

/**
 * Phase 06 post authorization. Central visibility logic stays in the
 * PostAccessService shared by every service; the policy adds ownership
 * gates for write actions.
 */
#[UsePolicy(Post::class)]
class PostPolicy
{
    public function __construct(private readonly PostAccessService $access) {}

    public function view(User $viewer, Post $post): bool
    {
        return $this->access->canView($viewer, $post);
    }

    public function update(User $viewer, Post $post): bool
    {
        return $viewer->is($post->user) && $post->deleted_at === null && $post->status !== 'removed';
    }

    public function delete(User $viewer, Post $post): bool
    {
        return $viewer->is($post->user) && $post->deleted_at === null;
    }

    public function publish(User $viewer, Post $post): bool
    {
        return $this->update($viewer, $post) && $post->status === 'draft';
    }

    public function like(User $viewer, Post $post): bool
    {
        return $this->access->canReact($viewer, $post);
    }

    public function comment(User $viewer, Post $post): bool
    {
        return $this->access->canComment($viewer, $post);
    }

    public function save(User $viewer, Post $post): bool
    {
        return $this->access->canView($viewer, $post) && $post->status === 'published';
    }

    public function share(User $viewer, Post $post): bool
    {
        return $this->access->canShare($viewer, $post);
    }

    public function hide(User $viewer, Post $post): bool
    {
        return ! $viewer->is($post->user) && $this->access->canView($viewer, $post);
    }
}
