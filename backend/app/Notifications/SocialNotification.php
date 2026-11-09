<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base class for social notifications. Delivery is queued and dispatched
 * after the surrounding database transaction commits so a rollback never
 * sends a phantom notification. Payloads contain orientation data only —
 * never account status, emails or other sensitive fields.
 */
abstract class SocialNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Move to the queue only after the writes that caused it commit. */
    public function __construct(public readonly User $actor)
    {
        $this->afterCommit = true;
    }

    /** Stable notification type identifier. */
    public static function type(): string
    {
        return 'social.';
    }

    /** Where the recipient should land after clicking. */
    public function navigationUrl(): string
    {
        return "/people/{$this->actor->username}";
    }

    public function toArray(User $notifiable): array
    {
        return [
            'type' => static::type(),
            'actor' => [
                'id' => $this->actor->getKey(),
                'username' => $this->actor->username,
                'display_name' => $this->actor->profile?->display_name ?? $this->actor->name,
            ],
            'url' => $this->navigationUrl(),
        ];
    }

    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /** Envelope data for duplicate-prevention checks. */
    public static function dedupeKey(int $actorId): string
    {
        return static::type().':'.$actorId;
    }
}
