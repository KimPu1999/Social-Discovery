<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\User;
use App\Notifications\PostLikedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Like / unlike reactions with unique edges, batched-safe counts. */
class PostReactionService
{
    public function __construct(
        private readonly PostAccessService $access,
        private readonly \App\Services\Social\SocialNotificationService $notifications,
    ) {}

    /** Idempotent like. Returns [liked, count]. */
    public function like(User $actor, Post $post): array
    {
        if (! $this->access->canReact($actor, $post)) {
            throw ValidationException::withMessages(['post' => 'You cannot like this post.']);
        }

        return DB::transaction(function () use ($actor, $post): array {
            // Serialize against concurrent likes of the same pair.
            DB::table('posts')->where('id', $post->getKey())->lockForUpdate()->get();

            $already = $post->reactions()->where('user_id', $actor->getKey())->exists();

            if (! $already) {
                $post->reactions()->create([
                    'user_id' => $actor->getKey(),
                    'reaction_type' => 'like',
                ]);

                // Never notify a user about their own action on their own post.
                if (! $actor->is($post->user)) {
                    $this->notifications->postLiked($post->user, $actor, $post);
                }
            }

            return [true, $post->reactions()->count()];
        });
    }

    /** Unlike your own reaction only. Returns [liked, count]. */
    public function unlike(User $actor, Post $post): array
    {
        if (! $this->access->canReact($actor, $post)) {
            throw ValidationException::withMessages(['post' => 'You cannot unlike this post.']);
        }

        return DB::transaction(function () use ($actor, $post): array {
            $post->reactions()->where('user_id', $actor->getKey())->delete();

            return [false, $post->reactions()->count()];
        });
    }
}
