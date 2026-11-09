<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UsePolicy(\App\Policies\PostPolicy::class)]
class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'body', 'visibility', 'status', 'published_at', 'edited_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'edited_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    /** Posts a viewer is allowed to see (visibility + status + soft delete). */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        $query->where('status', 'published')->whereNotNull('published_at');

        if ($viewer === null) {
            return $query->where('visibility', 'public');
        }

        $viewerId = (int) $viewer->getKey();

        return $query->where(static function (Builder $sub) use ($viewerId): void {
            $sub->where('visibility', 'public')
                ->orWhere(static function (Builder $own) use ($viewerId): void {
                    $own->where('user_id', $viewerId);
                })
                ->orWhere(static function (Builder $connected) use ($viewerId): void {
                    // Accepted connections are symmetric pairs stored canonically.
                    $connected->where('visibility', 'connections')->whereExists(static function ($pair) use ($viewerId): void {
                        $pair->selectRaw(1)
                            ->from('user_connections as uc')
                            ->whereRaw('((uc.user_one_id = ? AND uc.user_two_id = posts.user_id) OR (uc.user_two_id = ? AND uc.user_one_id = posts.user_id))', [$viewerId, $viewerId]);
                    });
                });
        });
    }

    /** Filter out blocked-pair authors and socially inactive accounts. */
    public function scopeEligibleFor(Builder $query, ?User $viewer): Builder
    {
        if ($viewer === null) {
            return $query;
        }

        return app(\App\Services\Feed\PostAccessService::class)->scopeEligible($query, $viewer);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('sort_order');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(PostShare::class, 'original_post_id');
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(PostHashtag::class, 'post_hashtag_post');
    }

    /** The share record when this post is a "share to feed" copy. */
    public function shareOf(): HasOne
    {
        return $this->hasOne(PostShare::class, 'shared_post_id');
    }

    /** Direct share-to-feed children of the original post. */
    public function resharedAs(): HasMany
    {
        return $this->hasMany(PostShare::class, 'original_post_id');
    }
}
