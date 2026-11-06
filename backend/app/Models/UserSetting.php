<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $fillable = ['user_id', 'language', 'theme', 'email_notifications', 'push_notifications'];
    protected function casts(): array { return ['email_notifications' => 'boolean', 'push_notifications' => 'boolean']; }
}
