<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportCardJob;
use App\Models\AcademicTerm;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Services\ReportCardService;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ReportCardController extends Controller
{
    public function __construct(private ReportCardService $service) {}

    /**
     * Class + term selector to manage report cards.
     */
    public function index(): View
    {
        $classes = SchoolClass::orderBy('name')->get();
        $terms   = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $current = AcademicTerm::current();

        return view('admin.report-cards.index', compact('classes', 'terms', 'current'));
    }

    /**
     * List all report cards for a class+term, show generation controls.
     */
    public function classCards(Request $request): View
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'term_id'  => 'required|exists:academic_terms,id',
        ]);

        $class   = SchoolClass::findOrFail($request->class_id);
        $term    = AcademicTerm::with('academicYear')->findOrFail($request->term_id);

        $cards = ReportCard::with('student')
            ->where('school_class_id', $class->id)
            ->where('term_id', $term->id)
            ->get()
            ->sortBy('student.first_name');

        // Surface any in-progress batch for this class+term
        $batchId = session("report_card_batch_{$class->id}_{$term->id}");
        $batch   = $batchId ? Bus::findBatch($batchId) : null;

        return view('admin.report-cards.class', compact('class', 'term', 'cards', 'batch'));
    }

    /**
     * Recompute statistics for a class+term (positions, averages, aggregate).
     */
    public function computeStats(Request $request): RedirectResponse
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'term_id'  => 'required|exists:academic_terms,id',
        ]);

        $class = SchoolClass::findOrFail($request->class_id);
        $term  = AcademicTerm::findOrFail($request->term_id);

        $this->service->computeClassStatistics($class, $term);

        return redirect()->route('admin.report-cards.class', [
            'class_id' => $class->id,
            'term_id'  => $term->id,
        ])->with('success', 'Statistics recomputed. Positions and averages are now updated.');
    }

    /**
     * Dispatch a batched job to generate ALL PDFs for a class.
     * Returns immediately; progress tracked via batchStatus().
     */
    public function generateAll(Request $request): RedirectResponse
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'term_id'  => 'required|exists:academic_terms,id',
        ]);

        $class = SchoolClass::findOrFail($request->class_id);
        $term  = AcademicTerm::findOrFail($request->term_id);

        // Recompute stats first (synchronously — fast DB operation)
        $this->service->computeClassStatistics($class, $term);

        $cards = ReportCard::with('student')
            ->where('school_class_id', $class->id)
            ->where('term_id', $term->id)
            ->get();

        if ($cards->isEmpty()) {
            return redirect()->route('admin.report-cards.class', [
                'class_id' => $class->id,
                'term_id'  => $term->id,
            ])->with('error', 'No report cards found. Compute statistics first.');
        }

        $jobs = $cards->map(fn (ReportCard $card) => new GenerateReportCardJob($card));

        $batch = Bus::batch($jobs)
            ->name("report-cards:class-{$class->id}:term-{$term->id}")
            ->allowFailures()
            ->dispatch();

        // Store batch ID in session so classCards() can surface progress
        session(["report_card_batch_{$class->id}_{$term->id}" => $batch->id]);

        return redirect()->route('admin.report-cards.class', [
            'class_id' => $class->id,
            'term_id'  => $term->id,
        ])->with('success', "Generating {$cards->count()} report card(s) in the background. This page will update automatically.");
    }

    /**
     * JSON endpoint: return batch progress for polling.
     * GET /report-cards/batch-status?batch_id=xxx
     */
    public function batchStatus(Request $request): JsonResponse
    {
        $batch = Bus::findBatch($request->query('batch_id', ''));

        if (! $batch) {
            return response()->json(['error' => 'Batch not found'], 404);
        }

        // Ownership check: batch names are "report-cards:class-{id}:term-{id}".
        // Extract class_id and verify it belongs to the current tenant so that
        // a school admin cannot poll another school's batch by guessing a UUID.
        if (preg_match('/^report-cards:class-(\d+):term-(\d+)$/', $batch->name, $m)) {
            $classOwned = SchoolClass::where('id', $m[1])->exists(); // HasTenantScope applies
            if (! $classOwned) {
                return response()->json(['error' => 'Batch not found'], 404);
            }
        }

        return response()->json([
            'id'               => $batch->id,
            'name'             => $batch->name,
            'total'            => $batch->totalJobs,
            'pending'          => $batch->pendingJobs,
            'processed'        => $batch->processedJobs(),
            'failed'           => $batch->failedJobs,
            'progress'         => $batch->progress(),   // 0–100
            'finished'         => $batch->finished(),
            'cancelled'        => $batch->cancelled(),
            'finished_at'      => $batch->finishedAt?->toIso8601String(),
        ]);
    }

    /**
     * Regenerate PDF for a single student.
     */
    public function generate(ReportCard $reportCard): RedirectResponse
    {
        $this->service->generatePdf($reportCard);

        return back()->with('success', 'Report card PDF generated.');
    }

    /**
     * Download a single student's report card PDF.
     */
    public function download(ReportCard $reportCard): HttpResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        if (! $reportCard->pdf_path || ! Storage::disk('local')->exists($reportCard->pdf_path)) {
            return back()->with('error', 'PDF not yet generated. Please generate it first.');
        }

        $filename = sprintf(
            '%s_ReportCard_Term%d.pdf',
            str_replace(' ', '_', $reportCard->student?->full_name ?? 'Student'),
            $reportCard->term?->term_number
        );

        return response()->download(
            Storage::disk('local')->path($reportCard->pdf_path),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Show remarks editor for a single report card.
     */
    public function editRemarks(ReportCard $reportCard): View
    {
        $reportCard->load(['student', 'schoolClass', 'term.academicYear']);
        return view('admin.report-cards.remarks', compact('reportCard'));
    }

    public function updateRemarks(Request $request, ReportCard $reportCard): RedirectResponse
    {
        $data = $request->validate([
            'class_teacher_remark' => 'nullable|string|max:1000',
            'headmaster_remark'    => 'nullable|string|max:1000',
        ]);

        $reportCard->update($data);

        return back()->with('success', 'Remarks saved.');
    }
}
