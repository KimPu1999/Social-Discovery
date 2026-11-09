<?php

namespace App\Notifications;

final class NewFollowerNotification extends SocialNotification
{
    public static function type(): string
    {
        return 'new_follower';
    }
}
