<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\PostShare;
use App\Models\User;
use App\Notifications\PostSharedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Share to my feed": creates a new post by the sharer that references the
 * original post via a single post_shares row (source attribution). No
 * media duplication and no recursive share chains. Shares of private or
 * connections-only posts are possible only to the permitted audience —
 * the original body/media are never copied, so they can never leak.
 */
class PostSharingService
{
    public function __construct(
        private readonly PostAccessService $access,
        private readonly HashtagService $hashtags,
        private readonly \App\Services\Social\SocialNotificationService $notifications,
    ) {}

    /** Create the share post + attribution row inside one transaction. */
    public function share(User $actor, Post $original, array $data): Post
    {
        if (! $this->access->canShare($actor, $original)) {
            throw ValidationException::withMessages(['post' => 'You cannot share this post.']);
        }

        return DB::transaction(function () use ($actor, $original, $data): Post {
            $sharedPost = Post::query()->create([
                'user_id' => $actor->getKey(),
                'body' => $data['body'] ?? null,
                'visibility' => $data['visibility'] ?? 'public',
                'status' => 'published',
                'published_at' => now(),
            ]);

            PostShare::query()->create([
                'user_id' => $actor->getKey(),
                'original_post_id' => $original->getKey(),
                'shared_post_id' => $sharedPost->getKey(),
            ]);

            $this->hashtags->syncForPost($sharedPost);

            // Notify the original author about the public share of their post.
            if (! $actor->is($original->user)) {
                $this->notifications->postShared($original->user, $actor, $sharedPost);
            }

            return $sharedPost;
        });
    }

    /** Hide the original from the reader when the source becomes unavailable. */
    public function attributionFor(User $viewer, Post $sharedPost): ?Post
    {
        $share = $sharedPost->shareOf;

        if ($share === null) {
            return null;
        }

        $original = $share->originalPost;

        if ($original === null || ! $this->access->canView($viewer, $original) || $original->status !== 'published') {
            return null;
        }

        return $original->loadMissing(['media', 'user.profile', 'hashtags']);
    }
}
