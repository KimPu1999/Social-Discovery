<?php

namespace App\Notifications;

final class ConnectionRequestNotification extends SocialNotification
{
    public static function type(): string
    {
        return 'connection_request';
    }

    public function navigationUrl(): string
    {
        return '/connections?tab=incoming';
    }
}
