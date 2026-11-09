<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostMedia;
use App\Services\Feed\PostMediaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Authorized media delivery and attachment removal. Every media byte
 * leaves through this controller: it enforces the owning post's
 * visibility for the requesting viewer before streaming from storage.
 */
class PostMediaController extends Controller
{
    public function __construct(private readonly PostMediaService $media) {}

    /** GET /feed/media/{uuid} — streamed, viewer-checked. */
    public function show(string $uuid)
    {
        $media = PostMedia::query()->where('uuid', $uuid)->first();

        if ($media === null) {
            abort(404);
        }

        try {
            Gate::authorize('view', $media->post);
        } catch (AuthorizationException) {
            abort(404);
        }

        $disk = Storage::disk($media->storage_disk);

        if (! $disk->exists($media->storage_path)) {
            abort(404);
        }

        // Private content: never cached by shared proxies.
        $filename = 'post-image-'.substr($media->uuid, 0, 8).'.'.match ($media->mime_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return $disk->response($media->storage_path, $filename, [
            'Cache-Control' => 'private, max-age=604800, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** DELETE /feed/media/{uuid} — author removes one attachment. */
    public function destroy(string $uuid): JsonResponse
    {
        $media = PostMedia::query()->where('uuid', $uuid)->first();

        if ($media === null) {
            abort(404);
        }

        try {
            Gate::authorize('update', $media->post);
        } catch (AuthorizationException) {
            abort(404);
        }

        $this->media->detach($media->post, [$media->getKey()]);

        return response()->json(['message' => 'Attachment removed.', 'removed' => true]);
    }
}
