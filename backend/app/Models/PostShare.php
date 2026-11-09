<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "share to my feed" event: the shared post is a brand new post that
 * points back to one original post via `shared_post_id` → post_shares.
 */
class PostShare extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'original_post_id', 'shared_post_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function originalPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'original_post_id');
    }

    public function sharedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'shared_post_id');
    }
}
