<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Discovery impressions retention: impressions exist only to support
 * recommendation quality and are pruned after $days. Run daily via the
 * scheduler (see routes/console.php).
 */
class PruneDiscoveryImpressions extends Command
{
    protected $signature = 'discovery:prune-impressions {--days=90 : Retention window in days}';

    protected $description = 'Delete discovery impressions older than the retention window';

    public function handle(): int
    {
        $days = max((int) $this->option('days'), 1);
        $cutoff = now()->subDays($days);

        $deleted = DB::table('discovery_impressions')->where('viewed_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} impressions (retention {$days} days).");

        return self::SUCCESS;
    }
}
