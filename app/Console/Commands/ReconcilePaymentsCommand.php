<?php

namespace App\Console\Commands;

use App\Services\ReconciliationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Payment reconciliation artisan command.
 *
 * Usage:
 *   php artisan payments:reconcile                            # current month
 *   php artisan payments:reconcile --from=2026-01-01 --to=2026-03-31
 *   php artisan payments:reconcile --heal                     # auto-heal orphaned payments
 *   php artisan payments:reconcile --tenant=42               # single tenant only
 */
class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile
                            {--from=  : Start date (YYYY-MM-DD). Defaults to first day of current month.}
                            {--to=    : End date (YYYY-MM-DD). Defaults to today.}
                            {--tenant= : Tenant ID to reconcile (omit for all tenants)}
                            {--heal   : Attempt to auto-heal orphaned payments}';

    protected $description = 'Reconcile payment records against subscriptions and report anomalies.';

    public function __construct(private readonly ReconciliationService $reconciliation)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $from   = $this->option('from') ? Carbon::parse($this->option('from')) : Carbon::now()->startOfMonth();
        $to     = $this->option('to')   ? Carbon::parse($this->option('to'))   : Carbon::now();
        $tenant = null;

        if ($this->option('tenant')) {
            $tenant = \App\Models\Tenant::find((int) $this->option('tenant'));
            if (! $tenant) {
                $this->error("Tenant #{$this->option('tenant')} not found.");
                return self::FAILURE;
            }
        }

        $this->info("Reconciling payments from {$from->toDateString()} to {$to->toDateString()}…");

        $report = $this->reconciliation->reconcile($from, $to, $tenant);

        // ── Summary table ─────────────────────────────────────────────────────
        $this->table(['Metric', 'Value'], [
            ['Period',              "{$report['period']['from']} → {$report['period']['to']}"],
            ['Total payments',      $report['total_payments']],
            ['Successful',          $report['successful']],
            ['Failed',              $report['failed']],
            ['Pending',             $report['pending']],
            ['Total revenue (GHS)', number_format($report['total_amount'], 2)],
        ]);

        // ── Anomalies ─────────────────────────────────────────────────────────
        $orphans    = $report['orphaned_payments'];
        $duplicates = $report['duplicate_refs'];
        $unactivated = $report['unactivated_subs'];

        if ($orphans->isNotEmpty()) {
            $this->warn("\n⚠️  {$orphans->count()} orphaned payment(s) (successful but no subscription linked):");
            $this->table(
                ['ID', 'Reference', 'Amount', 'Tenant', 'Gateway', 'Date'],
                $orphans->map(fn ($p) => [
                    $p->id, $p->reference,
                    'GHS ' . number_format($p->amount, 2),
                    $p->tenant_id, $p->gateway,
                    $p->created_at->toDateTimeString(),
                ])->toArray()
            );
        }

        if ($duplicates->isNotEmpty()) {
            $this->warn("\n⚠️  {$duplicates->count()} duplicate reference(s):");
            $this->table(
                ['Reference', 'Count', 'Total'],
                $duplicates->map(fn ($d) => [
                    $d->reference, $d->count,
                    'GHS ' . number_format($d->total, 2),
                ])->toArray()
            );
        }

        if ($unactivated->isNotEmpty()) {
            $this->warn("\n⚠️  {$unactivated->count()} subscription(s) marked active without a payment:");
            $this->table(
                ['Sub ID', 'Tenant', 'Amount', 'Activated At'],
                $unactivated->map(fn ($s) => [
                    $s->id, $s->tenant_id,
                    'GHS ' . number_format($s->amount, 2),
                    $s->activated_at?->toDateTimeString() ?? 'N/A',
                ])->toArray()
            );
        }

        if ($orphans->isEmpty() && $duplicates->isEmpty() && $unactivated->isEmpty()) {
            $this->info("\n✓ No anomalies found. Payments and subscriptions are in sync.");
        }

        // ── Gateway breakdown ─────────────────────────────────────────────────
        if ($report['gateway_breakdown']->isNotEmpty()) {
            $this->line("\nGateway breakdown:");
            $this->table(
                ['Gateway', 'Transactions', 'Total (GHS)'],
                $report['gateway_breakdown']->map(fn ($g) => [
                    $g->gateway, $g->count,
                    number_format($g->total, 2),
                ])->toArray()
            );
        }

        // ── Auto-heal ─────────────────────────────────────────────────────────
        if ($this->option('heal') && $orphans->isNotEmpty()) {
            $this->info("\nAttempting auto-heal of orphaned payments…");
            $healed = $this->reconciliation->autoHeal($from, $to);
            $this->info("✓ Auto-healed {$healed} payment(s).");
        }

        return self::SUCCESS;
    }
}
