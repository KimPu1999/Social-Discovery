<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An image attached to a post. Served through authorized delivery only —
 * never through unrestricted static storage URLs.
 */
class PostMedia extends Model
{
    public const MAX_BYTES = 5 * 1024 * 1024; // 5 MB per image
    public const MAX_PER_POST = 10;
    public const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    protected $fillable = [
        'uuid', 'post_id', 'media_type', 'storage_disk', 'storage_path',
        'mime_type', 'file_size', 'width', 'height', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer', 'width' => 'integer', 'height' => 'integer', 'sort_order' => 'integer'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** Authorized, viewer-checked URL returned by API resources. */
    public function authorizedUrl(): string
    {
        return url('/api/v1/feed/media/'.$this->uuid);
    }
}
