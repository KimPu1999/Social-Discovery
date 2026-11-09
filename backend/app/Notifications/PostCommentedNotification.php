<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;

/** Someone commented on the recipient's post. */
class PostCommentedNotification extends SocialNotification
{
    public function __construct(User $actor, public readonly Post $post, public readonly ?PostComment $comment)
    {
        parent::__construct($actor);
    }

    public static function type(): string
    {
        return 'post.commented';
    }

    public function navigationUrl(): string
    {
        return '/posts/'.$this->post->getKey();
    }

    public function toArray(User $notifiable): array
    {
        return parent::toArray($notifiable) + [
            'post_id' => (int) $this->post->getKey(),
            'comment_id' => $this->comment !== null ? (int) $this->comment->getKey() : null,
        ];
    }
}
