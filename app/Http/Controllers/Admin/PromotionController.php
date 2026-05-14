<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * End-of-year student promotion.
 *
 * Allows admins to bulk-move all active students from one class to another
 * (e.g. Basic 4 → Basic 5) at the start of a new academic year.
 *
 * Safety:
 * - Only active students are promoted.
 * - A preview step shows exactly who will be moved before any changes are made.
 * - Graduated students (moved out of the highest class) are set to status=graduated
 *   if no target class is selected.
 */
class PromotionController extends Controller
{
    public function index(): View
    {
        $classes = SchoolClass::withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get();

        return view('admin.students.promotion', compact('classes'));
    }

    /**
     * Preview: show which students will move.
     */
    public function preview(Request $request): View
    {
        $request->validate([
            'from_class_id' => 'required|exists:school_classes,id',
            'to_class_id'   => 'nullable|exists:school_classes,id|different:from_class_id',
            'action'        => 'required|in:promote,graduate',
        ]);

        $fromClass = SchoolClass::findOrFail($request->from_class_id);
        $toClass   = $request->to_class_id ? SchoolClass::find($request->to_class_id) : null;
        $action    = $request->action;

        $students = Student::where('school_class_id', $fromClass->id)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        $classes = SchoolClass::orderBy('name')->get();

        return view('admin.students.promotion', compact(
            'classes', 'fromClass', 'toClass', 'action', 'students'
        ));
    }

    /**
     * Execute the promotion.
     */
    public function execute(Request $request): RedirectResponse
    {
        $request->validate([
            'from_class_id' => 'required|exists:school_classes,id',
            'to_class_id'   => 'nullable|exists:school_classes,id',
            'action'        => 'required|in:promote,graduate',
            'confirmed'     => 'required|accepted',
        ]);

        $fromClass = SchoolClass::findOrFail($request->from_class_id);
        $action    = $request->action;

        $students = Student::where('school_class_id', $fromClass->id)
            ->where('status', 'active')
            ->get();

        if ($students->isEmpty()) {
            return redirect()->route('admin.promotion')
                ->with('error', 'No active students found in ' . $fromClass->full_name . '.');
        }

        if ($action === 'promote') {
            $toClass = SchoolClass::findOrFail($request->to_class_id);

            Student::where('school_class_id', $fromClass->id)
                ->where('status', 'active')
                ->update(['school_class_id' => $toClass->id]);

            $msg = "{$students->count()} student(s) promoted from {$fromClass->full_name} to {$toClass->full_name}.";

        } else {
            // Graduate: mark as graduated, keep in current class (historical record)
            Student::where('school_class_id', $fromClass->id)
                ->where('status', 'active')
                ->update(['status' => 'graduated']);

            $msg = "{$students->count()} student(s) from {$fromClass->full_name} marked as graduated.";
        }

        return redirect()->route('admin.students')
            ->with('success', $msg);
    }
}
