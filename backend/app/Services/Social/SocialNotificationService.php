<?php

namespace App\Services\Social;

use App\Models\User;
use App\Notifications\ConnectionAcceptedNotification;
use App\Notifications\ConnectionRequestNotification;
use App\Notifications\NewFollowerNotification;
use App\Notifications\SocialNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

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
        $exists = $notifiable->notifications()
            ->where('type', $notification::class)
            ->whereNull('read_at')
            ->where('data->type', $notification::type())
            ->where('data->actor->id', $notification->actor->getKey())
            ->exists();

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
