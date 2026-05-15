<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\LessonNote;
use App\Models\SchemeOfWork;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LessonNoteController extends Controller
{
    // ── Index — view all notes across all teachers ────────────────────────────

    public function index(Request $request): View
    {
        $terms    = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $classes  = SchoolClass::orderBy('name')->get();
        $teachers = Teacher::orderBy('first_name')->get();

        $currentTerm = AcademicTerm::current() ?? $terms->first();

        $selectedTermId    = $request->integer('term_id', $currentTerm?->id ?? 0);
        $selectedClassId   = $request->integer('class_id');
        $selectedTeacherId = $request->integer('teacher_id');
        $selectedStatus    = (string) $request->input('status', '');
        $selectedType      = (string) $request->input('type', '');

        $query = LessonNote::with(['teacher', 'schoolClass', 'subject', 'term'])
            ->where('term_id', $selectedTermId);

        if ($selectedClassId) {
            $query->where('school_class_id', $selectedClassId);
        }
        if ($selectedTeacherId) {
            $query->where('teacher_id', $selectedTeacherId);
        }
        if ($selectedStatus) {
            $query->where('status', $selectedStatus);
        }
        if ($selectedType) {
            $query->where('type', $selectedType);
        }

        $notes = $query->orderByDesc('lesson_date')->orderByDesc('created_at')->paginate(30)->withQueryString();

        // Stats for the selected term
        $stats = [
            'total'    => LessonNote::where('term_id', $selectedTermId)->count(),
            'draft'    => LessonNote::where('term_id', $selectedTermId)->where('status', 'draft')->count(),
            'submitted'=> LessonNote::where('term_id', $selectedTermId)->where('status', 'submitted')->count(),
            'approved' => LessonNote::where('term_id', $selectedTermId)->where('status', 'approved')->count(),
            'revision' => LessonNote::where('term_id', $selectedTermId)->where('status', 'revision_requested')->count(),
        ];

        return view('admin.lesson-notes.index', compact(
            'notes', 'terms', 'classes', 'teachers', 'stats',
            'selectedTermId', 'selectedClassId', 'selectedTeacherId',
            'selectedStatus', 'selectedType'
        ));
    }

    // ── Show + Approve / Reject ───────────────────────────────────────────────

    public function show(int $id): View
    {
        $note = LessonNote::with([
            'teacher', 'schoolClass', 'subject', 'term.academicYear',
            'strand', 'subStrand', 'attachments', 'approvedBy',
        ])->findOrFail($id);

        return view('admin.lesson-notes.show', compact('note'));
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $note = LessonNote::findOrFail($id);

        if ($note->status !== 'submitted') {
            return back()->with('error', 'Only submitted lesson notes can be approved.');
        }

        $note->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'revision_notes' => null,
        ]);

        return redirect()->route('admin.lesson-notes.show', $note->id)
            ->with('success', "Lesson note approved — \"{$note->title}\".");
    }

    public function requestRevision(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'revision_notes' => ['required', 'string', 'max:1000'],
        ]);

        $note = LessonNote::findOrFail($id);

        $note->update([
            'status'         => 'revision_requested',
            'revision_notes' => $data['revision_notes'],
            'approved_at'    => null,
            'approved_by'    => null,
        ]);

        return redirect()->route('admin.lesson-notes.show', $note->id)
            ->with('success', 'Revision requested. The teacher has been notified.');
    }

    // ── Schemes of work (admin view only) ────────────────────────────────────

    public function schemes(Request $request): View
    {
        $terms    = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $classes  = SchoolClass::orderBy('name')->get();
        $teachers = Teacher::orderBy('first_name')->get();

        $currentTerm    = AcademicTerm::current() ?? $terms->first();
        $selectedTermId = $request->integer('term_id', $currentTerm?->id ?? 0);

        $query = SchemeOfWork::with(['teacher', 'schoolClass', 'subject', 'term'])
            ->where('term_id', $selectedTermId);

        if ($request->integer('class_id')) {
            $query->where('school_class_id', $request->integer('class_id'));
        }
        if ($request->integer('teacher_id')) {
            $query->where('teacher_id', $request->integer('teacher_id'));
        }

        $schemes = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.lesson-notes.schemes', compact(
            'schemes', 'terms', 'classes', 'teachers', 'selectedTermId'
        ));
    }

    public function schemeShow(int $id): View
    {
        $scheme = SchemeOfWork::with(['teacher', 'schoolClass', 'subject', 'term.academicYear', 'weeks'])
            ->findOrFail($id);

        return view('admin.lesson-notes.scheme-show', compact('scheme'));
    }
}
