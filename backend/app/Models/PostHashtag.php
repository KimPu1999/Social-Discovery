<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PostHashtag extends Model
{
    protected $fillable = ['name', 'slug'];

    /** Most-used hashtags first (for trending lists). */
    public function scopePopular(Builder $query): Builder
    {
        return $query->withCount('posts')->orderByDesc('posts_count');
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_hashtag_post');
    }
}
