<?php

namespace App\Services;

use App\Mail\ReportCardReadyMail;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ReportCardService
{
    public function __construct(private GradeCalculator $grader) {}

    /**
     * Recompute class statistics (positions, averages, high/low) for an entire class
     * after all scores have been entered for a term.
     *
     * Call this ONCE after all teachers have submitted scores — not on every score save.
     */
    public function computeClassStatistics(SchoolClass $class, AcademicTerm $term): void
    {
        $students  = $class->students()->where('status', 'active')->get();
        $subjectIds = $class->subjects()->pluck('id');

        // ── Per-subject statistics ────────────────────────────────────
        foreach ($subjectIds as $subjectId) {
            $assessments = Assessment::where('school_class_id', $class->id)
                ->where('subject_id', $subjectId)
                ->where('term_id', $term->id)
                ->get();

            if ($assessments->isEmpty()) continue;

            $scores   = $assessments->pluck('total_score', 'student_id')->toArray();
            $avg      = round(array_sum($scores) / count($scores), 2);
            $highest  = max($scores);
            $lowest   = min($scores);
            $positions = $this->grader->computePositions($scores);

            foreach ($assessments as $assessment) {
                $assessment->update([
                    'class_average'    => $avg,
                    'highest_score'    => $highest,
                    'lowest_score'     => $lowest,
                    'position_in_class'=> $positions[$assessment->student_id] ?? null,
                ]);
            }
        }

        // ── Overall class position (aggregate of all subjects) ────────
        $studentTotals = [];

        foreach ($students as $student) {
            $grades = Assessment::where('student_id', $student->id)
                ->where('school_class_id', $class->id)
                ->where('term_id', $term->id)
                ->pluck('grade')
                ->filter()
                ->values()
                ->toArray();

            if (! empty($grades)) {
                $studentTotals[$student->id] = $this->grader->aggregate($grades, count($grades));
            }
        }

        // Lower aggregate = better position (like BECE scoring)
        asort($studentTotals);
        $overallPositions = [];
        $rank = 1; $prev = null; $skip = 0;
        foreach ($studentTotals as $sid => $agg) {
            if ($agg !== $prev) { $rank += $skip; $skip = 0; }
            $overallPositions[$sid] = $rank;
            $prev = $agg;
            $skip++;
        }

        $classSize = count($studentTotals);

        // ── Pre-load attendance and assessment counts in bulk (avoids N+1) ──
        $studentIds = $students->pluck('id');

        // All attendance rows for these students this term — loaded once
        $allAttendance = Attendance::where('term_id', $term->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy('student_id');

        // Assessment counts per student — single aggregation query
        $subjectCounts = Assessment::where('school_class_id', $class->id)
            ->where('term_id', $term->id)
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, COUNT(*) as cnt')
            ->groupBy('student_id')
            ->pluck('cnt', 'student_id');

        // ── Upsert report card headers ────────────────────────────────
        foreach ($students as $student) {
            $studentRecords = $allAttendance->get($student->id, collect());
            $attended       = $studentRecords->count();
            $present        = $studentRecords->where('status', 'present')->count();
            $subjectCount   = (int) $subjectCounts->get($student->id, 0);

            ReportCard::updateOrCreate(
                [
                    'tenant_id'  => $student->tenant_id,
                    'student_id' => $student->id,
                    'term_id'    => $term->id,
                ],
                [
                    'school_class_id'     => $class->id,
                    'total_subjects'      => $subjectCount,
                    'overall_position'    => $overallPositions[$student->id] ?? null,
                    'out_of'              => $classSize,
                    'attendance_present'  => $present,
                    'attendance_total'    => $attended,
                ]
            );
        }
    }

    /**
     * Generate the PDF for a single student's report card.
     * Saves to storage and stores path on the ReportCard record.
     */
    public function generatePdf(ReportCard $reportCard): string
    {
        $reportCard->load([
            'student.tenant',
            'schoolClass.classTeacher',
            'term.academicYear',
        ]);

        $assessments = Assessment::with('subject')
            ->where('student_id', $reportCard->student_id)
            ->where('term_id', $reportCard->term_id)
            ->orderBy('subject_id')
            ->get();

        $grades = $assessments->pluck('grade')->filter()->toArray();
        $aggregate = ! empty($grades) ? $this->grader->aggregate($grades, count($grades)) : null;

        // Resolve tenant — attached to student or fall back to app binding.
        // Parentheses around the ternary are required to avoid PHP's ?? / ?: precedence trap.
        $tenant       = $reportCard->student->tenant
            ?? (app()->bound('currentTenant') ? app('currentTenant') : null);
        $primaryColor = (($tenant?->primary_color ?? '') !== '') ? $tenant->primary_color : '#1a3a6e';

        // Resolve per-tenant CA/Exam maxes for label display on the PDF
        $caMax   = GradeCalculator::tenantCaMax($tenant);
        $examMax = GradeCalculator::tenantExamMax($tenant);

        $data = [
            'reportCard'   => $reportCard,
            'student'      => $reportCard->student,
            'class'        => $reportCard->schoolClass,
            'term'         => $reportCard->term,
            'assessments'  => $assessments,
            'aggregate'    => $aggregate,
            'gradeScale'   => GradeCalculator::tenantScale($tenant),
            'tenant'       => $tenant,
            'primaryColor' => $primaryColor,
            'caMax'        => $caMax,
            'examMax'      => $examMax,
        ];

        $pdf = Pdf::loadView('pdf.report-card', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'     => 'sans-serif',
                'isRemoteEnabled' => false,
                'dpi'             => 150,
            ]);

        $filename  = sprintf(
            'report-cards/%d/%d_%s_term%d.pdf',
            $reportCard->student->tenant_id,
            $reportCard->student->id,
            str_replace(' ', '_', $reportCard->student->full_name),
            $reportCard->term->term_number
        );

        Storage::disk('local')->put($filename, $pdf->output());

        $reportCard->update([
            'pdf_path'     => $filename,
            'generated_at' => now(),
        ]);

        Log::info('Report card PDF generated', [
            'student_id'    => $reportCard->student_id,
            'term_id'       => $reportCard->term_id,
            'path'          => $filename,
        ]);

        // Notify guardian that the report card is ready
        $guardianEmail = $reportCard->student?->guardian_email;
        if ($guardianEmail) {
            try {
                Mail::to($guardianEmail)->queue(new ReportCardReadyMail($reportCard));
            } catch (\Throwable $e) {
                // Non-fatal — PDF was generated successfully; log and continue
                Log::error('ReportCardReadyMail failed', [
                    'report_card_id' => $reportCard->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        return $filename;
    }

    /**
     * Generate PDFs for all students in a class for a given term.
     * Returns count of generated PDFs.
     */
    public function generateAllForClass(SchoolClass $class, AcademicTerm $term): int
    {
        // First recompute statistics so positions are fresh
        $this->computeClassStatistics($class, $term);

        $cards = ReportCard::with('student')
            ->where('school_class_id', $class->id)
            ->where('term_id', $term->id)
            ->get();

        $count = 0;
        foreach ($cards as $card) {
            try {
                $this->generatePdf($card);
                $count++;
            } catch (\Throwable $e) {
                Log::error('Report card PDF failed', [
                    'student_id' => $card->student_id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
