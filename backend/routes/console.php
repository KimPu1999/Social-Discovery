<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 04: impressions are quality-signal data with a bounded retention window.
Schedule::command('discovery:prune-impressions --days=90')->dailyAt('03:30');

// Phase 06: orphaned post media files are pruned once a day.
Schedule::job(new \App\Jobs\PruneOrphanPostMedia)->dailyAt('04:00');
