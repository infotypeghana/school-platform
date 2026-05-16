<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Prunes request_logs entries older than the configured retention window.
 *
 * Uses DB::table()->delete() (not Eloquent) to bypass the RequestLog
 * immutability guard — that guard prevents accidental single-record deletion
 * via $model->delete(), but bulk retention pruning is an intentional
 * system operation.
 *
 * Scheduled: nightly at 01:00 WAT (UTC).
 *
 * Usage:
 *   php artisan logs:prune-requests            # Prune with default 90-day retention
 *   php artisan logs:prune-requests --days=30  # Prune older than 30 days
 *   php artisan logs:prune-requests --dry-run  # Show count without deleting
 */
class PruneRequestLogsCommand extends Command
{
    protected $signature = 'logs:prune-requests
                            {--days=90    : Retention window in days (rows older than this are deleted)}
                            {--dry-run    : Show how many rows would be deleted without actually deleting}
                            {--chunk=5000 : Delete in batches of this size to avoid lock contention}';

    protected $description = 'Delete request_logs entries older than the retention window (default 90 days)';

    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $dryRun  = (bool) $this->option('dry-run');
        $chunk   = (int) $this->option('chunk');
        $cutoff  = now()->subDays($days)->toDateTimeString();

        $this->info(sprintf(
            '%s request_logs older than %d days (cutoff: %s)...',
            $dryRun ? '[DRY RUN] Would delete' : 'Pruning',
            $days,
            $cutoff
        ));

        // Count first — always shown
        $count = DB::table('request_logs')
            ->where('created_at', '<', $cutoff)
            ->count();

        $this->line("  → {$count} rows eligible for deletion.");

        if ($count === 0) {
            $this->info('  Nothing to prune.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('  Dry-run: no rows deleted.');
            return self::SUCCESS;
        }

        // Chunked deletion to avoid locking the table for a full-table scan
        $deleted = 0;
        $this->output->progressStart($count);

        do {
            // Fetch IDs for this chunk, then delete them
            // This is more lock-friendly than a direct WHERE DELETE on large tables
            $ids = DB::table('request_logs')
                ->where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $batchDeleted = DB::table('request_logs')
                ->whereIn('id', $ids)
                ->delete();

            $deleted += $batchDeleted;
            $this->output->progressAdvance($batchDeleted);
        } while ($ids->count() === $chunk);

        $this->output->progressFinish();

        $this->info("  ✓ Pruned {$deleted} rows from request_logs.");

        Log::info('[REQUEST_LOG_PRUNE] Pruning complete', [
            'rows_deleted'   => $deleted,
            'cutoff'         => $cutoff,
            'retention_days' => $days,
        ]);

        return self::SUCCESS;
    }
}
