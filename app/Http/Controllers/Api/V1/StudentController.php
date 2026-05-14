<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * GET /api/v1/students
     *
     * Query params:
     *   class_id   — filter by school class
     *   status     — active|inactive (default: active)
     *   search     — name or admission number
     *   per_page   — default 25
     */
    public function index(Request $request): JsonResponse
    {
        $query = Student::with('schoolClass:id,name,section')
            ->where('status', $request->get('status', 'active'));

        if ($classId = $request->get('class_id')) {
            $query->where('school_class_id', $classId);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name',  'like', "%{$search}%")
                  ->orWhere('admission_number', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        $students = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage);

        return response()->json([
            'data' => $students->map(fn ($s) => [
                'id'               => $s->id,
                'admission_number' => $s->admission_number,
                'full_name'        => $s->full_name,
                'first_name'       => $s->first_name,
                'last_name'        => $s->last_name,
                'gender'           => $s->gender,
                'date_of_birth'    => $s->date_of_birth?->toDateString(),
                'status'           => $s->status,
                'photo_url'        => $s->photo ? asset('storage/' . $s->photo) : null,
                'class'            => $s->schoolClass ? [
                    'id'   => $s->schoolClass->id,
                    'name' => $s->schoolClass->full_name,
                ] : null,
            ]),
            'meta' => [
                'total'        => $students->total(),
                'per_page'     => $students->perPage(),
                'current_page' => $students->currentPage(),
                'last_page'    => $students->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/students/{id}
     *
     * Returns full student profile with recent attendance and fee summary.
     * Uses manual resolution (not route model binding) so HasTenantScope
     * is applied after the api.tenant middleware has bound currentTenant.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $student = Student::findOrFail($id);
        $student->load([
            'schoolClass:id,name,section',
            'attendances' => fn ($q) => $q->latest('date')->limit(30),
            'fees'        => fn ($q) => $q->latest()->limit(10),
        ]);

        $attendanceSummary = [
            'present' => $student->attendances->where('status', 'present')->count(),
            'absent'  => $student->attendances->where('status', 'absent')->count(),
            'late'    => $student->attendances->where('status', 'late')->count(),
        ];

        $feeSummary = [
            'total_billed' => $student->fees->sum('amount'),
            'total_paid'   => $student->fees->sum('amount_paid'),
            'balance'      => $student->fees->sum('amount') - $student->fees->sum('amount_paid'),
        ];

        return response()->json([
            'id'               => $student->id,
            'admission_number' => $student->admission_number,
            'full_name'        => $student->full_name,
            'first_name'       => $student->first_name,
            'last_name'        => $student->last_name,
            'gender'           => $student->gender,
            'date_of_birth'    => $student->date_of_birth?->toDateString(),
            'status'           => $student->status,
            'admission_date'   => $student->admission_date?->toDateString(),
            'photo_url'        => $student->photo ? asset('storage/' . $student->photo) : null,
            'guardian_name'    => $student->guardian_name,
            'guardian_phone'   => $student->guardian_phone,
            'guardian_email'   => $student->guardian_email,
            'address'          => $student->address,
            'class'            => $student->schoolClass ? [
                'id'   => $student->schoolClass->id,
                'name' => $student->schoolClass->full_name,
            ] : null,
            'attendance_summary' => $attendanceSummary,
            'fee_summary'        => $feeSummary,
        ]);
    }
}
