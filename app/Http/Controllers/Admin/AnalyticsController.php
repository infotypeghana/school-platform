<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $terms   = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $classes = SchoolClass::orderBy('name')->get();

        $termId  = $request->input('term_id');
        $classId = $request->input('class_id');

        $data = $this->buildAnalytics($termId, $classId);

        return view('admin.analytics.index', array_merge(compact('terms', 'classes', 'termId', 'classId'), $data));
    }

    /**
     * Export analytics data as CSV.
     */
    public function exportCsv(Request $request): Response
    {
        $termId  = $request->input('term_id');
        $classId = $request->input('class_id');
        $data    = $this->buildAnalytics($termId, $classId);

        $filename = 'analytics_' . now()->format('Y-m-d') . '.csv';
        $rows     = [];

        // Subject performance
        $rows[] = ['Subject Performance'];
        $rows[] = ['Subject', 'Avg Score', 'Count'];
        foreach ($data['subjectPerformance'] as $row) {
            $rows[] = [$row->subject?->name ?? 'Unknown', $row->avg_score, $row->count];
        }

        $rows[] = [];
        $rows[] = ['Grade Distribution'];
        $rows[] = ['Grade', 'Count'];
        foreach (array_combine($data['gradeLabels'], $data['gradeCounts']) as $grade => $count) {
            $rows[] = [$grade, $count];
        }

        $rows[] = [];
        $rows[] = ['Gender Performance'];
        $rows[] = ['Gender', 'Avg Score', 'Count'];
        foreach (['male', 'female'] as $gender) {
            $g = $data['genderPerf'][$gender] ?? null;
            $rows[] = [ucfirst($gender), $g?->avg_score ?? 'N/A', $g?->count ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Class Performance'];
        $rows[] = ['Class', 'Avg Score', 'Count'];
        foreach ($data['classPerf'] as $row) {
            $rows[] = [$row->schoolClass?->full_name ?? 'Unknown', $row->avg_score, $row->count];
        }

        $rows[] = [];
        $rows[] = ['Pass/Fail Summary'];
        $rows[] = ['Total Assessments', $data['totalCount']];
        $rows[] = ['Pass (C6 or better)', $data['passCount']];
        $rows[] = ['Pass Rate', ($data['passRate'] ?? 0) . '%'];

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $row)) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export analytics as PDF.
     */
    public function exportPdf(Request $request): \Illuminate\Http\Response
    {
        $termId  = $request->input('term_id');
        $classId = $request->input('class_id');
        $data    = $this->buildAnalytics($termId, $classId);
        $tenant  = app('currentTenant');

        $term    = $termId  ? AcademicTerm::with('academicYear')->find($termId)   : null;
        $class   = $classId ? SchoolClass::find($classId) : null;

        $pdf = Pdf::loadView('pdf.analytics', array_merge($data, compact('tenant', 'term', 'class')))
            ->setPaper('a4', 'portrait');

        $filename = 'analytics_' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    // ── Shared query builder (used by index + both exports) ───────────────────

    private function buildAnalytics(?string $termId, ?string $classId): array
    {
        $tenant   = app('currentTenant');
        $cacheKey = "analytics:{$tenant->id}:t{$termId}:c{$classId}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($termId, $classId) {
            // Subject Performance
            $subjectPerformance = Assessment::when($termId,  fn ($q) => $q->where('term_id', $termId))
                ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
                ->select('subject_id', DB::raw('ROUND(AVG(total_score), 1) as avg_score'), DB::raw('COUNT(*) as count'))
                ->whereNotNull('subject_id')
                ->groupBy('subject_id')
                ->with('subject')
                ->get()
                ->sortByDesc('avg_score');

            // Grade Distribution
            $gradeOrder = ['A1','B2','B3','C4','C5','C6','D7','E8','F9'];
            $gradeDist  = Assessment::when($termId,  fn ($q) => $q->where('term_id', $termId))
                ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
                ->select('grade', DB::raw('COUNT(*) as total'))
                ->whereNotNull('grade')
                ->groupBy('grade')
                ->get()
                ->keyBy('grade');

            $gradeLabels = $gradeOrder;
            $gradeCounts = array_map(fn ($g) => (int) ($gradeDist[$g]->total ?? 0), $gradeOrder);

            // Gender Performance
            $genderPerf = Assessment::join('students', 'assessments.student_id', '=', 'students.id')
                ->when($termId,  fn ($q) => $q->where('assessments.term_id', $termId))
                ->when($classId, fn ($q) => $q->where('assessments.school_class_id', $classId))
                ->select('students.gender', DB::raw('ROUND(AVG(total_score), 1) as avg_score'), DB::raw('COUNT(*) as count'))
                ->whereNotNull('students.gender')
                ->groupBy('students.gender')
                ->get()
                ->keyBy('gender');

            // Class Performance
            $classPerf = Assessment::when($termId, fn ($q) => $q->where('term_id', $termId))
                ->select('school_class_id', DB::raw('ROUND(AVG(total_score), 1) as avg_score'), DB::raw('COUNT(*) as count'))
                ->whereNotNull('school_class_id')
                ->groupBy('school_class_id')
                ->with('schoolClass')
                ->get()
                ->sortByDesc('avg_score');

            // Term Trend
            $termTrend = Assessment::when($classId, fn ($q) => $q->where('school_class_id', $classId))
                ->select('term_id', DB::raw('ROUND(AVG(total_score), 1) as avg_score'))
                ->groupBy('term_id')
                ->with('term.academicYear')
                ->get()
                ->sortBy('term_id')
                ->take(8);

            // Pass / Fail
            $passGrades = ['A1','B2','B3','C4','C5','C6'];
            $totalCount = Assessment::when($termId,  fn ($q) => $q->where('term_id', $termId))
                ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
                ->whereNotNull('grade')->count();
            $passCount  = Assessment::when($termId,  fn ($q) => $q->where('term_id', $termId))
                ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
                ->whereIn('grade', $passGrades)->count();
            $passRate   = $totalCount > 0 ? round(($passCount / $totalCount) * 100, 1) : null;

            // Student Summary
            $studentCounts = [
                'total'  => Student::count(),
                'active' => Student::where('status', 'active')->count(),
                'male'   => Student::where('gender', 'male')->count(),
                'female' => Student::where('gender', 'female')->count(),
            ];

            return compact(
                'subjectPerformance',
                'gradeLabels', 'gradeCounts',
                'genderPerf',
                'classPerf',
                'termTrend',
                'passRate', 'passCount', 'totalCount',
                'studentCounts',
            );
        });
    }
}
