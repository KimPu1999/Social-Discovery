<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPrivacySetting extends Model
{
    protected $fillable = ['user_id', 'profile_visibility', 'discovery_enabled', 'show_online_status', 'allow_message_requests', 'allow_connection_requests', 'show_interests', 'show_location'];
    protected function casts(): array { return ['discovery_enabled' => 'boolean', 'show_online_status' => 'boolean', 'allow_message_requests' => 'boolean', 'allow_connection_requests' => 'boolean', 'show_interests' => 'boolean', 'show_location' => 'boolean']; }
}
