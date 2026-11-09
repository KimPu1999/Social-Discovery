<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveryPreference extends Model
{
    protected $fillable = ['user_id', 'discovery_enabled', 'show_in_recommendations', 'preferred_interest_categories'];

    protected function casts(): array
    {
        return [
            'discovery_enabled' => 'boolean',
            'show_in_recommendations' => 'boolean',
            'preferred_interest_categories' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
