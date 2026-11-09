<?php

namespace App\Jobs;

use App\Services\Feed\PostMediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Safety net for orphaned media uploads: deletes stored image files the
 * `post_media` table no longer references (older than a day) — e.g. when
 * a process died between the storage write and the transaction commit.
 */
class PruneOrphanPostMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PostMediaService $mediaService): void
    {
        $mediaService->pruneStaleOrphans();
    }
}
