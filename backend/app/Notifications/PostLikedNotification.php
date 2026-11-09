<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;

/** Someone liked the recipient's post. */
class PostLikedNotification extends SocialNotification
{
    public function __construct(User $actor, public readonly Post $post)
    {
        parent::__construct($actor);
    }

    public static function type(): string
    {
        return 'post.liked';
    }

    public function navigationUrl(): string
    {
        return '/posts/'.$this->post->getKey();
    }

    public function toArray(User $notifiable): array
    {
        return parent::toArray($notifiable) + ['post_id' => (int) $this->post->getKey()];
    }
}
