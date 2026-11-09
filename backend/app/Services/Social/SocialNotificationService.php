<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Notifications\CommentRepliedNotification;
use App\Notifications\ConnectionAcceptedNotification;
use App\Notifications\ConnectionRequestNotification;
use App\Notifications\NewFollowerNotification;
use App\Notifications\PostCommentedNotification;
use App\Notifications\PostLikedNotification;
use App\Notifications\PostSharedNotification;
use App\Notifications\SocialNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for social notification persistence and reads.
 * Duplicate prevention: an unread notification of the same type from the
 * same actor is never re-sent. Delivery happens after transaction commit
 * because the notification classes opt into afterCommit queueing.
 */
class SocialNotificationService
{
    private const TYPES = [
        NewFollowerNotification::class,
        ConnectionRequestNotification::class,
        ConnectionAcceptedNotification::class,
    ];

    /** Send `notification` to `notifiable` unless an identical unread one exists. */
    public function send(User $notifiable, SocialNotification $notification): void
    {
        $query = $notifiable->notifications()
            ->where('type', $notification::class)
            ->whereNull('read_at');

        // Older PostgreSQL installations used the Laravel notification
        // default (`text`) for data. JSON operators cannot be applied to a
        // text column, so cast during the transition to jsonb. SQLite keeps
        // the JSON-path grammar used by the test database.
        if (DB::connection()->getDriverName() === 'pgsql') {
            $query
                ->whereRaw("(notifications.data::jsonb)->>'type' = ?", [$notification::type()])
                ->whereRaw("(notifications.data::jsonb)->'actor'->>'id' = ?", [(string) $notification->actor->getKey()]);
        } else {
            $query
                ->where('data->type', $notification::type())
                ->where('data->actor->id', $notification->actor->getKey());
        }

        $exists = $query->exists();

        if (! $exists) {
            $notifiable->notify($notification);
        }
    }

    public function newFollower(User $recipient, User $follower): void
    {
        $this->send($recipient, new NewFollowerNotification($follower));
    }

    public function connectionRequested(User $recipient, User $sender): void
    {
        $this->send($recipient, new ConnectionRequestNotification($sender));
    }

    public function connectionAccepted(User $recipient, User $acceptor): void
    {
        $this->send($recipient, new ConnectionAcceptedNotification($acceptor));
    }

    public function postLiked(User $recipient, User $actor, Post $post): void
    {
        $this->send($recipient, new PostLikedNotification($actor, $post));
    }

    public function postCommented(User $recipient, User $actor, Post $post, ?PostComment $comment = null): void
    {
        $this->send($recipient, new PostCommentedNotification($actor, $post, $comment));
    }

    public function commentReplied(User $recipient, User $actor, Post $post, ?PostComment $reply = null): void
    {
        $this->send($recipient, new CommentRepliedNotification($actor, $post, $reply));
    }

    public function postShared(User $recipient, User $actor, Post $sharedPost): void
    {
        $this->send($recipient, new PostSharedNotification($actor, $sharedPost));
    }

    /** Paginated notification feed for a user (server-side pagination). */
    public function feed(User $user, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $user->notifications()->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /** Only the recipient may mark a notification read. */
    public function markRead(User $user, string $notificationId): bool
    {
        $notification = DatabaseNotification::query()->whereKey($notificationId)->first();

        if (! $notification || $notification->notifiable_type !== User::class || (int) $notification->notifiable_id !== (int) $user->getKey()) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    public function markAllRead(User $user): void
    {
        $user->unreadNotifications->each->markAsRead();
    }
}
