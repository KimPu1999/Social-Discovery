<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;

/** Someone shared the recipient's post into their own feed. */
class PostSharedNotification extends SocialNotification
{
    public function __construct(User $actor, public readonly Post $sharedPost)
    {
        parent::__construct($actor);
    }

    public static function type(): string
    {
        return 'post.shared';
    }

    public function navigationUrl(): string
    {
        return '/posts/'.$this->sharedPost->getKey();
    }

    public function toArray(User $notifiable): array
    {
        return parent::toArray($notifiable) + ['post_id' => (int) $this->sharedPost->getKey()];
    }
}
