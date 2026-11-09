<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Secure image attachments for posts.
 *
 * Validation is content-based, never extension-based: real MIME via finfo,
 * decoded pixel data via GD, dimension bounds and byte size. Accepted files
 * are re-encoded through GD before storage, which strips EXIF/metadata
 * (GPS included) and neutralizes any payload that is not a genuine raster
 * image. Filenames are random and directories per-post, so no
 * user-controlled string ever reaches a filesystem path.
 */
class PostMediaService
{
    private const DISK = 'local';
    private const MAX_DIMENSION = 8000;
    private const REENCODE_QUALITY = 86;
    private const STALE_HOURS = 24;

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Validate and store images for a post. Runs inside the outer post
     * transaction; DB rows roll back with it, but storage writes are cleaned
     * up explicitly here so failures never orphan files.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, PostMedia>
     */
    public function attach(Post $post, array $files): array
    {
        $stored = [];

        try {
            $sortOrder = (int) $post->media()->count();

            foreach (array_values($files) as $file) {
                $payload = $this->process($file);

                $stored[] = PostMedia::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'post_id' => $post->getKey(),
                    'media_type' => 'image',
                    'storage_disk' => self::DISK,
                    'storage_path' => $payload['path'],
                    'mime_type' => $payload['mime'],
                    'file_size' => $payload['bytes'],
                    'width' => $payload['width'],
                    'height' => $payload['height'],
                    'sort_order' => $sortOrder,
                ]);

                $sortOrder++;
            }
        } catch (\Throwable $exception) {
            foreach ($stored as $media) {
                Storage::disk(self::DISK)->delete($media->storage_path);
            }

            throw $exception;
        }

        return $stored;
    }

    /** Remove detached attachments during post editing (rows + files). */
    public function detach(Post $post, array $mediaIds): void
    {
        $media = $post->media()->whereIn('id', array_map(intval(...), $mediaIds))->get();

        foreach ($media as $item) {
            Storage::disk($item->storage_disk)->delete($item->storage_path);
            $item->delete();
        }
    }

    /** Delete media with its file (post teardown or moderator removal). */
    public function deleteForPost(Post $post): void
    {
        $post->media()->get()->each(static function (PostMedia $media): void {
            Storage::disk($media->storage_disk)->delete($media->storage_path);
            $media->forceDelete();
        });
    }

    /**
     * Background cleanup: delete stored files older than a day that no
     * post_media row references (a safety net for process-level crashes
     * between storage write and transaction commit).
     */
    public function pruneStaleOrphans(): int
    {
        $pruned = 0;
        $cutoff = now()->subHours(self::STALE_HOURS)->getTimestamp();

        foreach (Storage::disk(self::DISK)->allFiles('posts') as $path) {
            $lastModified = Storage::disk(self::DISK)->lastModified($path);

            if ($lastModified >= $cutoff) {
                continue;
            }

            $referenced = PostMedia::query()->where('storage_path', $path)->exists();

            if (! $referenced) {
                Storage::disk(self::DISK)->delete($path);
                $pruned++;
            }
        }

        return $pruned;
    }

    /**
     * @return array{path: string, mime: string, bytes: int, width: int, height: int}
     */
    private function process(UploadedFile $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw ValidationException::withMessages(['media' => 'The file could not be read.']);
        }

        $bytes = (string) file_get_contents($file->getRealPath());

        if ($bytes === '' || mb_strlen($bytes) > PostMedia::MAX_BYTES) {
            throw ValidationException::withMessages(['media' => 'Each image may be at most 5 MB.']);
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            throw ValidationException::withMessages(['media' => 'Only JPEG, PNG, or WebP images are allowed.']);
        }

        $mime = (string) ($info['mime'] ?? '');

        if (! in_array($mime, PostMedia::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages(['media' => 'Only JPEG, PNG, or WebP images are allowed.']);
        }

        [$width, $height] = [(int) $info[0], (int) $info[1]];

        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw ValidationException::withMessages(['media' => 'Image dimensions are out of range.']);
        }

        $reencoded = $this->reencode($bytes, $mime);

        if ($reencoded === null) {
            throw ValidationException::withMessages(['media' => 'That image could not be processed.']);
        }

        $disk = Storage::disk(self::DISK);
        $path = sprintf('posts/%s/%s.%s', (string) Str::uuid(), (string) Str::uuid(), self::EXTENSIONS[$mime]);

        $disk->put($path, $reencoded['bytes']);

        return [
            'path' => $path,
            'mime' => $mime,
            'bytes' => $reencoded['bytes'],
            'width' => $reencoded['width'],
            'height' => $reencoded['height'],
        ];
    }

    /**
     * Re-encode a validated image through GD, stripping all metadata.
     *
     * @return array{bytes: string, width: int, height: int}|null
     */
    private function reencode(string $bytes, string $mime): ?array
    {
        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $canvas = imagecreatetruecolor($width, $height);

        if ($mime !== 'image/jpeg') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }

        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        $temporary = tempnam(sys_get_temp_dir(), 'pmedia');

        try {
            $encoded = match ($mime) {
                'image/jpeg' => imagejpeg($canvas, $temporary, self::REENCODE_QUALITY),
                'image/png' => imagepng($canvas, $temporary, 6),
                'image/webp' => imagewebp($canvas, $temporary, self::REENCODE_QUALITY),
                default => false,
            };

            imagedestroy($canvas);
            imagedestroy($source);

            if (! $encoded) {
                return null;
            }

            return [
                'bytes' => (string) file_get_contents($temporary),
                'width' => $width,
                'height' => $height,
            ];
        } finally {
            @unlink($temporary);
        }
    }
}
