<?php

namespace App\Notifications;

final class ConnectionAcceptedNotification extends SocialNotification
{
    public static function type(): string
    {
        return 'connection_accepted';
    }
}
