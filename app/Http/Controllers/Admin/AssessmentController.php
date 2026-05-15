<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Services\GradeCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    /**
     * Class + term selector.
     */
    public function index(): View
    {
        $classes = SchoolClass::with('classTeacher')->get();
        $terms   = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $current = AcademicTerm::current();

        return view('admin.assessments.index', compact('classes', 'terms', 'current'));
    }

    /**
     * Score entry grid: students × subjects for a given class + term.
     */
    public function edit(Request $request): View
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'term_id'  => 'required|exists:academic_terms,id',
        ]);

        $tenant   = app('currentTenant');
        $caMax    = GradeCalculator::tenantCaMax($tenant);
        $examMax  = GradeCalculator::tenantExamMax($tenant);

        $class    = SchoolClass::with(['students' => fn ($q) => $q->where('status', 'active')->orderBy('first_name'), 'subjects'])->findOrFail($request->class_id);
        $term     = AcademicTerm::with('academicYear')->findOrFail($request->term_id);
        $students = $class->students;
        $subjects = $class->subjects;

        // Pre-load existing assessments keyed by [student_id][subject_id]
        $existing = Assessment::where('school_class_id', $class->id)
            ->where('term_id', $term->id)
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->keyBy('subject_id'));

        return view('admin.assessments.edit', compact('class', 'term', 'students', 'subjects', 'existing', 'caMax', 'examMax'));
    }

    /**
     * Bulk-save scores from the grid.
     */
    public function update(Request $request): RedirectResponse
    {
        $tenant  = app('currentTenant');
        $caMax   = GradeCalculator::tenantCaMax($tenant);
        $examMax = GradeCalculator::tenantExamMax($tenant);

        $request->validate([
            'class_id'        => 'required|exists:school_classes,id',
            'term_id'         => 'required|exists:academic_terms,id',
            'scores'          => 'required|array',
            'scores.*.*'      => 'array',
            'scores.*.*.ca'   => "nullable|numeric|min:0|max:{$caMax}",
            'scores.*.*.exam' => "nullable|numeric|min:0|max:{$examMax}",
        ]);

        $classId  = $request->class_id;
        $termId   = $request->term_id;
        $tenantId = $tenant?->id;

        // Security: pre-load the valid student + subject IDs for this class so that
        // a crafted POST with foreign keys cannot inject scores for another class/tenant.
        $class           = SchoolClass::findOrFail($classId);
        $validStudentIds = $class->students()->pluck('students.id')->map(fn ($id) => (string) $id)->flip();
        $validSubjectIds = $class->subjects()->pluck('subjects.id')->map(fn ($id) => (string) $id)->flip();

        foreach ($request->scores as $studentId => $subjectScores) {
            if (! isset($validStudentIds[(string) $studentId])) {
                continue; // student does not belong to this class — skip
            }

            foreach ($subjectScores as $subjectId => $score) {
                if (! isset($validSubjectIds[(string) $subjectId])) {
                    continue; // subject does not belong to this class — skip
                }
                $ca   = isset($score['ca'])   && $score['ca']   !== '' ? (float) $score['ca']   : null;
                $exam = isset($score['exam']) && $score['exam'] !== '' ? (float) $score['exam'] : null;

                if ($ca === null && $exam === null) {
                    // Skip empty rows — don't create blank records
                    continue;
                }

                Assessment::updateOrCreate(
                    [
                        'tenant_id'       => $tenantId,
                        'student_id'      => $studentId,
                        'subject_id'      => $subjectId,
                        'term_id'         => $termId,
                        'school_class_id' => $classId,
                    ],
                    [
                        'ca_score'   => $ca   ?? 0,
                        'exam_score' => $exam ?? 0,
                    ]
                );
            }
        }

        return redirect()->route('admin.assessments.edit', [
            'class_id' => $classId,
            'term_id'  => $termId,
        ])->with('success', 'Scores saved successfully.');
    }
}
