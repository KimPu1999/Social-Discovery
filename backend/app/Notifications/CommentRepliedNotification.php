<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;

/** Someone replied to the recipient's comment. */
class CommentRepliedNotification extends SocialNotification
{
    public function __construct(User $actor, public readonly Post $post, public readonly ?PostComment $reply)
    {
        parent::__construct($actor);
    }

    public static function type(): string
    {
        return 'comment.replied';
    }

    public function navigationUrl(): string
    {
        return '/posts/'.$this->post->getKey();
    }

    public function toArray(User $notifiable): array
    {
        return parent::toArray($notifiable) + [
            'post_id' => (int) $this->post->getKey(),
            'comment_id' => $this->reply !== null ? (int) $this->reply->getKey() : null,
            'parent_id' => $this->reply?->parent_id !== null ? (int) $this->reply->parent_id : null,
        ];
    }
}
