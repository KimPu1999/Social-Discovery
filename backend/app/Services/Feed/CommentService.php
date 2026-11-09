<?php

namespace App\Services\Feed;

use App\Models\PostComment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Comments and one level of replies. Thread integrity: a reply's parent
 * must live on the same post AND be a top-level comment itself.
 */
class CommentService
{
    public function __construct(
        private readonly PostAccessService $access,
        private readonly \App\Services\Social\SocialNotificationService $notifications,
    ) {}

    /** Paginated top-level comments with replies, newest-first? threads: oldest first. */
    public function index(User $viewer, Post $post, int $perPage = 10): LengthAwarePaginator
    {
        return $post->comments()
            ->whereNull('parent_id')
            ->with(['user.profile', 'user:id,name,username', 'replies.user.profile', 'replies.user:id,name,username'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** New comment or reply. $parent set when this is a reply. */
    public function create(User $actor, Post $post, string $body, ?PostComment $parent = null): PostComment
    {
        if (! $this->access->canComment($actor, $post)) {
            throw ValidationException::withMessages(['post' => 'You cannot comment on this post.']);
        }

        if ($parent !== null) {
            $this->assertRepliable($post, $parent);
        }

        return DB::transaction(function () use ($actor, $post, $body, $parent): PostComment {
            $comment = PostComment::query()->create([
                'post_id' => $post->getKey(),
                'user_id' => $actor->getKey(),
                'parent_id' => $parent?->getKey(),
                'body' => $body,
            ]);

            $recipient = $parent !== null ? $parent->user : $post->user;

            if ($recipient !== null && ! $actor->is($recipient)) {
                if ($parent !== null) {
                    $this->notifications->commentReplied($recipient, $actor, $post, $comment);
                } else {
                    $this->notifications->postCommented($recipient, $actor, $post, $comment);
                }
            }

            return $comment;
        });
    }

    /** Edit own comment only; moderators go through moderation actions. */
    public function update(User $actor, PostComment $comment, string $body): PostComment
    {
        if (! $actor->is($comment->user)) {
            throw ValidationException::withMessages(['comment' => 'You can only edit your own comments.']);
        }

        $comment->forceFill(['body' => $body, 'edited_at' => now()])->save();

        return $comment;
    }

    /** Delete own comment; thread integrity: replies disappear with the parent. */
    public function delete(User $actor, PostComment $comment): void
    {
        if (! $actor->is($comment->user)) {
            throw ValidationException::withMessages(['comment' => 'You can only delete your own comments.']);
        }

        DB::transaction(function () use ($comment): void {
            $comment->replies()->each(fn ($reply) => $reply->delete());
            $comment->delete();
        });
    }

    /** Replies attach only to top-level comments of the same visible post. */
    private function assertRepliable(Post $post, PostComment $parent): void
    {
        if ((int) $parent->post_id !== (int) $post->getKey()) {
            throw ValidationException::withMessages(['parent' => 'That comment does not belong to this post.']);
        }

        if ($parent->deleted_at !== null) {
            throw ValidationException::withMessages(['parent' => 'That comment is no longer available.']);
        }

        if ($parent->parent_id !== null) {
            // One reply level — never a reply to a reply.
            throw ValidationException::withMessages(['parent' => 'Replies can only be added to top-level comments.']);
        }
    }
}
