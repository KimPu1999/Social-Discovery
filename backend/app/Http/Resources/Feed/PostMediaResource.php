<?php

namespace App\Http\Resources\Feed;

use App\Models\PostMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Authorized media delivery payload: URLs point through the authenticated
 * media endpoint so private/connections media is never exposed via a
 * static, unrestricted storage URL.
 */
class PostMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PostMedia $media */
        $media = $this->resource;

        return [
            'id' => (int) $media->getKey(),
            'media_type' => $media->media_type,
            'url' => $media->authorizedUrl(),
            'mime_type' => $media->mime_type,
            'width' => $media->width,
            'height' => $media->height,
            'sort_order' => (int) $media->sort_order,
        ];
    }
}
