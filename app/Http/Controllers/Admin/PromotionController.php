<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Promotion;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * End-of-year student promotion with full history tracking.
 *
 * Features:
 * - Every promotion run is recorded in the `promotions` table with the
 *   academic year, from/to class, action, and the admin who ran it.
 * - Per-student action control: each student can be individually set to
 *   Promote, Hold Back, Graduate, or Skip within a single batch.
 * - Double-promotion guard: students already processed for the selected
 *   academic year are flagged and excluded from the batch with a warning.
 * - Full history view filterable by year, class, and action.
 */
class PromotionController extends Controller
{
    // ── Step 1: Config form ───────────────────────────────────────────────────

    public function index(): View
    {
        $classes      = SchoolClass::withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get();
        $academicYears = AcademicYear::orderByDesc('id')->get();
        $currentYear   = AcademicYear::current();

        return view('admin.students.promotion', compact('classes', 'academicYears', 'currentYear'));
    }

    // ── Step 2: Preview (per-student action selection) ────────────────────────

    public function preview(Request $request): View
    {
        $request->validate([
            'from_class_id'    => ['required', 'exists:school_classes,id'],
            'to_class_id'      => ['nullable', 'exists:school_classes,id', 'different:from_class_id'],
            'default_action'   => ['required', 'in:promoted,held_back,graduated'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
        ]);

        $fromClass    = SchoolClass::findOrFail($request->from_class_id);
        $toClass      = $request->to_class_id ? SchoolClass::find($request->to_class_id) : null;
        $defaultAction = $request->default_action;
        $academicYear = AcademicYear::findOrFail($request->academic_year_id);

        // Load active students in this class
        $students = Student::where('school_class_id', $fromClass->id)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        // Flag which students have already been processed this academic year
        $alreadyPromotedIds = Promotion::where('academic_year_id', $academicYear->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->pluck('student_id')
            ->flip(); // use flip so we can use isset() for O(1) lookup

        $classes = SchoolClass::orderBy('name')->get();

        return view('admin.students.promotion', compact(
            'classes', 'fromClass', 'toClass', 'defaultAction',
            'academicYear', 'students', 'alreadyPromotedIds'
        ));
    }

    // ── Step 3: Execute the batch ─────────────────────────────────────────────

    public function execute(Request $request): RedirectResponse
    {
        $request->validate([
            'from_class_id'          => ['required', 'exists:school_classes,id'],
            'academic_year_id'       => ['required', 'exists:academic_years,id'],
            'students'               => ['required', 'array'],
            'students.*.action'      => ['required', 'in:promoted,held_back,graduated,skip'],
            'students.*.to_class_id' => ['nullable', 'exists:school_classes,id'],
            'students.*.notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $fromClass  = SchoolClass::findOrFail($request->from_class_id);
        $yearId     = (int) $request->academic_year_id;
        $tenantId   = app('currentTenant')?->id;
        $promotedBy = auth()->id();

        $processed      = 0;
        $alreadyDone    = [];
        $skipped        = 0;

        foreach ($request->students as $studentIdStr => $data) {
            $action = $data['action'];

            if ($action === 'skip') {
                $skipped++;
                continue;
            }

            $studentId = (int) $studentIdStr;

            // Security: student must belong to the stated from_class and be active
            $student = Student::where('id', $studentId)
                ->where('school_class_id', $fromClass->id)
                ->where('status', 'active')
                ->first();

            if (! $student) {
                continue; // tampered form or race condition — silently skip
            }

            // Double-promotion guard
            if (Promotion::where('student_id', $student->id)->where('academic_year_id', $yearId)->exists()) {
                $alreadyDone[] = $student->full_name;
                continue;
            }

            $toClassId = null;

            switch ($action) {
                case 'promoted':
                    $toClassId = ! empty($data['to_class_id']) ? (int) $data['to_class_id'] : null;
                    if ($toClassId) {
                        $student->update(['school_class_id' => $toClassId]);
                    }
                    break;

                case 'graduated':
                    $student->update(['status' => 'graduated']);
                    // school_class_id kept for historical reference; to_class_id = null
                    break;

                case 'held_back':
                    // Student stays in the same class — no Student column changes needed.
                    // We record to_class_id = from_class_id to make it queryable.
                    $toClassId = $fromClass->id;
                    break;
            }

            Promotion::create([
                'tenant_id'        => $tenantId,
                'student_id'       => $student->id,
                'academic_year_id' => $yearId,
                'from_class_id'    => $fromClass->id,
                'to_class_id'      => $toClassId,
                'action'           => $action,
                'promoted_by'      => $promotedBy,
                'notes'            => $data['notes'] ?? null,
            ]);

            $processed++;
        }

        // Build flash message
        $parts = [];
        if ($processed > 0) {
            $parts[] = "{$processed} student(s) processed.";
        }
        if ($skipped > 0) {
            $parts[] = "{$skipped} skipped.";
        }
        if (! empty($alreadyDone)) {
            $names = implode(', ', $alreadyDone);
            $parts[] = "Already processed this year (skipped): {$names}.";
        }

        $msg = $parts ? implode(' ', $parts) : 'No students were processed.';

        return redirect()->route('admin.students')
            ->with($processed > 0 ? 'success' : 'info', $msg);
    }

    // ── History ───────────────────────────────────────────────────────────────

    public function history(Request $request): View
    {
        $academicYears = AcademicYear::orderByDesc('id')->get();
        $classes       = SchoolClass::orderBy('name')->get();

        $query = Promotion::with(['student', 'fromClass', 'toClass', 'academicYear', 'promotedBy'])
            ->orderByDesc('created_at');

        if ($request->integer('academic_year_id')) {
            $query->where('academic_year_id', $request->integer('academic_year_id'));
        }
        if ($request->integer('from_class_id')) {
            $query->where('from_class_id', $request->integer('from_class_id'));
        }
        if ($request->input('action')) {
            $query->where('action', $request->input('action'));
        }

        $promotions = $query->paginate(30)->withQueryString();

        return view('admin.students.promotion-history', compact(
            'promotions', 'academicYears', 'classes'
        ));
    }
}
