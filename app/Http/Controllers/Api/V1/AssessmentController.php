<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    /**
     * GET /api/v1/assessments
     *
     * Query params:
     *   class_id   — filter by class
     *   subject_id — filter by subject
     *   term_id    — filter by term (required)
     *   student_id — filter to a single student
     *   per_page   — default 50
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'term_id' => 'required|integer|exists:academic_terms,id',
        ]);

        $query = Assessment::with([
            'student:id,first_name,last_name,admission_number',
            'subject:id,name',
            'schoolClass:id,name,section',
        ])->where('term_id', $request->get('term_id'));

        if ($classId = $request->get('class_id')) {
            $query->where('school_class_id', $classId);
        }

        if ($subjectId = $request->get('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        if ($studentId = $request->get('student_id')) {
            $query->where('student_id', $studentId);
        }

        $perPage = min((int) $request->get('per_page', 50), 100);

        $assessments = $query
            ->orderBy('school_class_id')
            ->orderBy('subject_id')
            ->paginate($perPage);

        return response()->json([
            'data' => $assessments->map(fn ($a) => [
                'id'          => $a->id,
                'student'     => $a->student ? [
                    'id'               => $a->student->id,
                    'full_name'        => $a->student->first_name . ' ' . $a->student->last_name,
                    'admission_number' => $a->student->admission_number,
                ] : null,
                'subject'     => $a->subject ? ['id' => $a->subject->id, 'name' => $a->subject->name] : null,
                'class'       => $a->schoolClass ? ['id' => $a->schoolClass->id, 'name' => $a->schoolClass->full_name] : null,
                'ca_score'    => $a->ca_score,
                'exam_score'  => $a->exam_score,
                'total_score' => $a->total_score,
                'grade'       => $a->grade,
                'remark'      => $a->remark,
            ]),
            'meta' => [
                'total'        => $assessments->total(),
                'per_page'     => $assessments->perPage(),
                'current_page' => $assessments->currentPage(),
                'last_page'    => $assessments->lastPage(),
            ],
        ]);
    }
}
