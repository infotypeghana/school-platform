<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * GET /api/v1/attendance
     *
     * Query params:
     *   class_id   — required
     *   date       — YYYY-MM-DD (default: today)
     *   term_id    — optional, defaults to current term
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|integer',
            'date'     => 'nullable|date',
        ]);

        $date    = $request->get('date', now()->toDateString());
        $classId = $request->get('class_id');

        $students = Student::where('school_class_id', $classId)
            ->where('status', 'active')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'admission_number', 'photo']);

        $attendance = Attendance::where('school_class_id', $classId)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        return response()->json([
            'date'     => $date,
            'class_id' => (int) $classId,
            'records'  => $students->map(fn ($s) => [
                'student_id'       => $s->id,
                'admission_number' => $s->admission_number,
                'full_name'        => $s->first_name . ' ' . $s->last_name,
                'photo_url'        => $s->photo ? asset('storage/' . $s->photo) : null,
                'status'           => $attendance->get($s->id)?->status ?? null,
                'remark'           => $attendance->get($s->id)?->remark ?? null,
            ]),
        ]);
    }

    /**
     * POST /api/v1/attendance
     *
     * Records attendance for a class on a given date.
     *
     * Body:
     * {
     *   class_id: 1,
     *   term_id: 2,
     *   date: "2026-05-14",
     *   records: [
     *     { student_id: 10, status: "present", remark: "" },
     *     ...
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id'          => 'required|integer|exists:school_classes,id',
            'term_id'           => 'required|integer|exists:academic_terms,id',
            'date'              => 'required|date',
            'records'           => 'required|array|min:1',
            'records.*.student_id' => 'required|integer',
            'records.*.status'     => 'required|in:present,absent,late,excused',
            'records.*.remark'     => 'nullable|string|max:200',
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Note: we use whereDate() instead of where('date') because the Eloquent
        // `date` cast persists as 'Y-m-d H:i:s' via fromDateTime(), making a plain
        // string equality check unreliable in SQLite.
        DB::transaction(function () use ($data, $tenantId) {
            foreach ($data['records'] as $record) {
                $existing = Attendance::where('student_id', $record['student_id'])
                    ->where('school_class_id', $data['class_id'])
                    ->whereDate('date', $data['date'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'term_id' => $data['term_id'],
                        'status'  => $record['status'],
                        'remark'  => $record['remark'] ?? null,
                    ]);
                } else {
                    Attendance::create([
                        'tenant_id'       => $tenantId,
                        'student_id'      => $record['student_id'],
                        'school_class_id' => $data['class_id'],
                        'term_id'         => $data['term_id'],
                        'date'            => $data['date'],
                        'status'          => $record['status'],
                        'remark'          => $record['remark'] ?? null,
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Attendance recorded successfully.',
            'date'    => $data['date'],
            'count'   => count($data['records']),
        ], 201);
    }
}
