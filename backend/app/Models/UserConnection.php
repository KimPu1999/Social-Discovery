<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Accepted connection stored with a canonical, unordered-safe pair:
 * `user_one_id` is always the smaller of the two ids.
 */
class UserConnection extends Model
{
    protected $fillable = ['user_one_id', 'user_two_id', 'connected_at'];

    protected function casts(): array
    {
        return ['connected_at' => 'datetime'];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /** Is one of the pair the given user? */
    public function involves(int $userId): bool
    {
        return $this->user_one_id === $userId || $this->user_two_id === $userId;
    }
}
