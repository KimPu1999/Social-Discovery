<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPrivacySetting extends Model
{
    protected $fillable = ['user_id', 'profile_visibility', 'discovery_enabled', 'show_online_status', 'allow_message_requests', 'allow_connection_requests', 'allow_follows', 'show_interests', 'show_followers', 'show_following', 'show_connections', 'show_mutual_connections', 'show_location'];

    protected function casts(): array
    {
        return ['discovery_enabled' => 'boolean', 'show_online_status' => 'boolean', 'allow_message_requests' => 'boolean', 'allow_connection_requests' => 'boolean', 'allow_follows' => 'boolean', 'show_interests' => 'boolean', 'show_followers' => 'boolean', 'show_following' => 'boolean', 'show_connections' => 'boolean', 'show_mutual_connections' => 'boolean', 'show_location' => 'boolean'];
    }
}
