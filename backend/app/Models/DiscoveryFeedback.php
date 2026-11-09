<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveryFeedback extends Model
{
    public const TYPE_NOT_INTERESTED = 'not_interested';

    public const TYPE_HIDE_PROFILE = 'hide_profile';

    protected $fillable = ['user_id', 'target_user_id', 'feedback_type'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
