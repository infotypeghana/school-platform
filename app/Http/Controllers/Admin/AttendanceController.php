<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * Class + date selector.
     */
    public function index(): View
    {
        $classes = SchoolClass::orderBy('name')->get();
        $today   = now()->format('Y-m-d');

        return view('admin.attendance.index', compact('classes', 'today'));
    }

    /**
     * Show attendance sheet for a class on a specific date.
     */
    public function sheet(Request $request): View
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'date'     => 'required|date|before_or_equal:today',
        ]);

        $class    = SchoolClass::with(['students' => fn ($q) => $q->where('status', 'active')->orderBy('first_name')])->findOrFail($request->class_id);
        $date     = $request->date;
        $term     = AcademicTerm::current();

        // Existing records for this date
        $existing = Attendance::where('school_class_id', $class->id)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        return view('admin.attendance.sheet', compact('class', 'date', 'term', 'existing'));
    }

    /**
     * Bulk-save attendance for a class on a date.
     */
    public function save(Request $request): RedirectResponse
    {
        $request->validate([
            'class_id'        => 'required|exists:school_classes,id',
            'term_id'         => 'nullable|exists:academic_terms,id',
            'date'            => 'required|date|before_or_equal:today',
            'attendance'      => 'required|array',
            'attendance.*'    => 'in:present,absent,late,excused',
        ]);

        $classId  = $request->class_id;
        $date     = $request->date;
        $termId   = $request->term_id;
        $tenantId = app('currentTenant')?->id;

        $sms      = app(SmsService::class);
        $tenant   = app('currentTenant');
        $school   = $tenant?->name ?? 'School';
        $dateLabel = date('d M Y', strtotime($date));

        // Security: ensure submitted student IDs all belong to the chosen class
        // (prevents a crafted POST from injecting attendance for another tenant's students).
        $validStudentIds = Student::where('school_class_id', $classId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        foreach ($request->attendance as $studentId => $status) {
            if (! in_array((string) $studentId, $validStudentIds, true)) {
                continue; // silently skip IDs that don't belong to this class
            }
            // The DB unique constraint is (tenant_id, student_id, date) — 3 columns.
            // school_class_id is NOT part of the key; including it in the match key
            // would cause a unique constraint violation if a student changes class
            // and attendance is saved for the same date again.
            $record = Attendance::updateOrCreate(
                [
                    'tenant_id'  => $tenantId,
                    'student_id' => $studentId,
                    'date'       => $date,
                ],
                [
                    'school_class_id' => $classId,
                    'status'          => $status,
                    'term_id'         => $termId,
                ]
            );

            // Notify guardian when student is marked absent (only on fresh absent, not updates)
            if ($status === 'absent' && $record->wasRecentlyCreated) {
                $student  = Student::find($studentId);
                $guardian = $student?->guardian_phone;

                if ($guardian && $student) {
                    $msg = "Dear Parent/Guardian, {$student->full_name} was ABSENT from {$school} on {$dateLabel}. Please contact the school if this is unexpected.";
                    $sms->send($guardian, $msg, $tenant, $record);
                }
            }
        }

        return redirect()->route('admin.attendance.sheet', [
            'class_id' => $classId,
            'date'     => $date,
        ])->with('success', 'Attendance saved for ' . date('d M Y', strtotime($date)) . '.');
    }

    /**
     * Summary report for a class over a date range.
     */
    public function report(Request $request): View
    {
        $request->validate([
            'class_id'   => 'required|exists:school_classes,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $class = SchoolClass::with(['students' => fn ($q) => $q->where('status', 'active')->orderBy('first_name')])->findOrFail($request->class_id);

        $records = Attendance::where('school_class_id', $class->id)
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->get();

        // Pivot: [student_id => ['present' => n, 'absent' => n, ...]]
        $summary = [];
        foreach ($class->students as $student) {
            $studentRecords = $records->where('student_id', $student->id);
            $summary[$student->id] = [
                'student'  => $student,
                'present'  => $studentRecords->where('status', 'present')->count(),
                'absent'   => $studentRecords->where('status', 'absent')->count(),
                'late'     => $studentRecords->where('status', 'late')->count(),
                'excused'  => $studentRecords->where('status', 'excused')->count(),
                'total'    => $studentRecords->count(),
            ];
        }

        $classes    = SchoolClass::orderBy('name')->get();
        $startDate  = $request->start_date;
        $endDate    = $request->end_date;

        return view('admin.attendance.report', compact('class', 'summary', 'classes', 'startDate', 'endDate'));
    }
}
