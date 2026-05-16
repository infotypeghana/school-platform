<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Prunes old audit_logs rows to prevent unbounded table growth.
 *
 * Runs monthly (scheduled in routes/console.php).
 *
 * Usage:
 *   php artisan audit:prune                # delete logs older than 365 days
 *   php artisan audit:prune --days=180     # delete logs older than 180 days
 *   php artisan audit:prune --dry-run      # show count without deleting
 *   php artisan audit:prune --tenant=5     # prune only for tenant_id=5
 */
class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune
                            {--days=365   : Delete logs older than this many days}
                            {--dry-run    : Count rows without deleting}
                            {--tenant=    : Restrict pruning to a single tenant_id}';

    protected $description = 'Delete old audit_logs rows (default: > 365 days).';

    public function handle(): int
    {
        $days      = (int) $this->option('days');
        $tenantId  = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $cutoff    = now()->subDays($days);

        $query = DB::table('audit_logs')->where('created_at', '<', $cutoff);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} audit_log row(s) older than {$days} days.");
            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('No audit logs to prune.');
            return self::SUCCESS;
        }

        // Delete in chunks to avoid long-running transactions
        $deleted = 0;
        while (true) {
            $batchIds = DB::table('audit_logs')
                ->where('created_at', '<', $cutoff)
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->limit(1000)
                ->pluck('id');

            if ($batchIds->isEmpty()) {
                break;
            }

            $n = DB::table('audit_logs')->whereIn('id', $batchIds)->delete();
            $deleted += $n;
        }

        $this->info("Pruned {$deleted} audit_log row(s) older than {$days} days.");

        Log::info('audit:prune completed', [
            'deleted'    => $deleted,
            'older_than' => $days . ' days',
            'tenant_id'  => $tenantId,
        ]);

        return self::SUCCESS;
    }
}
