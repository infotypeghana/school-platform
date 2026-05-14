<?php

namespace App\Jobs;

use App\Models\ReportCard;
use App\Models\Tenant;
use App\Services\ReportCardService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generate a single student's report card PDF.
 *
 * Designed to run inside a Bus::batch() for bulk generation with progress tracking.
 *
 * Usage (single):
 *   GenerateReportCardJob::dispatch($reportCard);
 *
 * Usage (batch with progress):
 *   $batch = Bus::batch($cards->map(fn ($c) => new GenerateReportCardJob($c)))
 *       ->name("report-cards:class-{$classId}:term-{$termId}")
 *       ->allowFailures()
 *       ->dispatch();
 *   session(['report_card_batch_id' => $batch->id]);
 */
class GenerateReportCardJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120; // PDF generation can take up to 2 minutes for heavy cards

    public function __construct(
        private readonly ReportCard $reportCard,
    ) {
        // Route this job to the dedicated PDF supervisor (180 s timeout, 2–4 workers)
        $this->onQueue('pdf');
    }

    public function handle(ReportCardService $service): void
    {
        // If the batch has been cancelled, skip this job
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Queue workers have no currentTenant binding — resolve it from the report card
        // so all tenant-scoped Eloquent queries (Assessment, Student, etc.) work correctly.
        $tenant = Tenant::find($this->reportCard->tenant_id);
        if ($tenant) {
            app()->instance('currentTenant', $tenant);
        }

        $service->generatePdf($this->reportCard);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateReportCardJob failed', [
            'report_card_id' => $this->reportCard->id,
            'student_id'     => $this->reportCard->student_id,
            'error'          => $e->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'report-cards',
            "student:{$this->reportCard->student_id}",
            "term:{$this->reportCard->term_id}",
        ];
    }
}
