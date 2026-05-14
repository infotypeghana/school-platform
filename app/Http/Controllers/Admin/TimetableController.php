<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timetable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableController extends Controller
{
    // ── Index — weekly grid for a selected class ──────────────────────────────

    public function index(Request $request): View
    {
        $classes    = SchoolClass::orderBy('name')->get();
        $classId    = $request->input('class_id', $classes->first()?->id);
        $class      = $classId ? SchoolClass::find($classId) : null;

        // Build a day×period matrix from DB entries for this class
        $entries = $class
            ? Timetable::where('school_class_id', $class->id)
                ->with(['subject', 'teacher'])
                ->get()
                ->keyBy(fn ($t) => "{$t->day_of_week}_{$t->period_number}")
            : collect();

        // Discover the period range (min 1..8, expand if DB has more)
        $maxPeriod = max(8, $entries->max('period_number') ?? 8);

        return view('admin.timetables.index', compact('classes', 'class', 'entries', 'maxPeriod'));
    }

    // ── Create form ───────────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        $classes  = SchoolClass::orderBy('name')->get();
        $teachers = Teacher::where('status', 'active')->orderBy('first_name')->get();

        $classId     = $request->input('class_id');
        $dayOfWeek   = $request->input('day');
        $periodNumber= $request->input('period');

        // Subjects for the pre-selected class (if any)
        $subjects = $classId
            ? Subject::where('school_class_id', $classId)->orderBy('name')->get()
            : collect();

        return view('admin.timetables.form', compact(
            'classes', 'teachers', 'subjects', 'classId', 'dayOfWeek', 'periodNumber'
        ));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'day_of_week'     => 'required|integer|between:1,5',
            'period_number'   => 'required|integer|between:1,9',
            'start_time'      => 'required|date_format:H:i',
            'end_time'        => 'required|date_format:H:i|after:start_time',
            'subject_id'      => 'nullable|exists:subjects,id',
            'teacher_id'      => 'nullable|exists:teachers,id',
            'label'           => 'nullable|string|max:80',
        ]);

        // Upsert — if the slot already exists, update it
        Timetable::updateOrCreate(
            [
                'school_class_id' => $data['school_class_id'],
                'day_of_week'     => $data['day_of_week'],
                'period_number'   => $data['period_number'],
            ],
            $data
        );

        return redirect()
            ->route('admin.timetables.index', ['class_id' => $data['school_class_id']])
            ->with('success', 'Period saved successfully.');
    }

    // ── Edit form ─────────────────────────────────────────────────────────────

    public function edit(Timetable $timetable): View
    {
        $classes  = SchoolClass::orderBy('name')->get();
        $teachers = Teacher::where('status', 'active')->orderBy('first_name')->get();
        $subjects = Subject::where('school_class_id', $timetable->school_class_id)
            ->orderBy('name')->get();

        return view('admin.timetables.form', compact(
            'timetable', 'classes', 'teachers', 'subjects'
        ));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, Timetable $timetable): RedirectResponse
    {
        $data = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'day_of_week'     => 'required|integer|between:1,5',
            'period_number'   => 'required|integer|between:1,9',
            'start_time'      => 'required|date_format:H:i',
            'end_time'        => 'required|date_format:H:i|after:start_time',
            'subject_id'      => 'nullable|exists:subjects,id',
            'teacher_id'      => 'nullable|exists:teachers,id',
            'label'           => 'nullable|string|max:80',
        ]);

        $timetable->update($data);

        return redirect()
            ->route('admin.timetables.index', ['class_id' => $timetable->school_class_id])
            ->with('success', 'Period updated.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(Timetable $timetable): RedirectResponse
    {
        $classId = $timetable->school_class_id;
        $timetable->delete();

        return redirect()
            ->route('admin.timetables.index', ['class_id' => $classId])
            ->with('success', 'Period removed.');
    }

    // ── Subjects for a class (AJAX helper) ───────────────────────────────────

    public function subjectsForClass(SchoolClass $schoolClass)
    {
        return response()->json(
            Subject::where('school_class_id', $schoolClass->id)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }
}
