<?php

namespace App\Console\Commands;

use App\Models\AcademicTerm;
use App\Services\InvoiceService;
use Illuminate\Console\Command;

class GenerateTermInvoicesCommand extends Command
{
    protected $signature = 'invoices:generate
                            {--term-id= : Generate for a specific term ID (defaults to current term)}
                            {--dry-run  : Preview without inserting}';

    protected $description = 'Generate term invoices for all active subscriptions that have a package attached';

    public function handle(InvoiceService $invoiceService): int
    {
        $termId = $this->option('term-id');
        $term   = $termId
            ? AcademicTerm::findOrFail($termId)
            : AcademicTerm::where('is_current', true)->first();

        if (! $term) {
            $this->error('No current academic term found. Use --term-id to specify one.');
            return self::FAILURE;
        }

        $this->info("Generating invoices for term: {$term->term_name} ({$term->start_date} → {$term->end_date})");

        if ($this->option('dry-run')) {
            $pending = \App\Models\Subscription::whereIn('status', ['active', 'trial'])
                ->whereNotNull('package_id')
                ->whereDoesntHave('invoices', fn ($q) => $q->where('term_id', $term->id))
                ->count();
            $this->info("[DRY RUN] Would generate {$pending} invoice(s).");
            return self::SUCCESS;
        }

        $count = $invoiceService->generateTermInvoices($term);
        $this->info("Generated {$count} invoice(s) successfully.");

        return self::SUCCESS;
    }
}
