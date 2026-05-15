<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\CurriculumStrand;
use App\Models\CurriculumSubStrand;
use App\Models\LessonNote;
use App\Models\LessonNoteAttachment;
use App\Models\SchemeOfWork;
use App\Models\SchemeOfWorkWeek;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LessonNotePortalController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return Teacher */
    private function authTeacher(): Teacher
    {
        /** @var Teacher $t */
        $t = view()->shared('authTeacher');
        return $t;
    }

    // ════════════════════════════════════════════════════════════════════════
    //  LESSON NOTES
    // ════════════════════════════════════════════════════════════════════════

    public function notesIndex(Request $request): View
    {
        $teacher     = $this->authTeacher();
        $terms       = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $currentTerm = AcademicTerm::current() ?? $terms->first();

        $selectedTermId = $request->integer('term_id', $currentTerm?->id ?? 0);
        $selectedStatus = (string) $request->input('status', '');

        $query = LessonNote::with(['schoolClass', 'subject', 'term'])
            ->where('teacher_id', $teacher->id)
            ->where('term_id', $selectedTermId);

        if ($selectedStatus) {
            $query->where('status', $selectedStatus);
        }

        $notes = $query->orderByDesc('lesson_date')->orderByDesc('created_at')->paginate(20)->withQueryString();

        $stats = [
            'total'     => LessonNote::where('teacher_id', $teacher->id)->where('term_id', $selectedTermId)->count(),
            'draft'     => LessonNote::where('teacher_id', $teacher->id)->where('term_id', $selectedTermId)->where('status', 'draft')->count(),
            'submitted' => LessonNote::where('teacher_id', $teacher->id)->where('term_id', $selectedTermId)->where('status', 'submitted')->count(),
            'approved'  => LessonNote::where('teacher_id', $teacher->id)->where('term_id', $selectedTermId)->where('status', 'approved')->count(),
            'revision'  => LessonNote::where('teacher_id', $teacher->id)->where('term_id', $selectedTermId)->where('status', 'revision_requested')->count(),
        ];

        return view('teacher-portal.lesson-notes.index', compact(
            'notes', 'terms', 'stats', 'selectedTermId', 'selectedStatus'
        ));
    }

    public function notesCreate(Request $request): View
    {
        $teacher = $this->authTeacher();

        $subjects = Subject::with('schoolClass')
            ->where('teacher_id', $teacher->id)
            ->orderBy('name')
            ->get();

        $classes = SchoolClass::whereHas('subjects', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->orWhere('class_teacher_id', $teacher->id)
            ->orderBy('name')
            ->get()
            ->unique('id');

        $terms   = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $current = AcademicTerm::current();

        $strands    = CurriculumStrand::with('subStrands')->orderBy('name')->get();
        $subStrands = CurriculumSubStrand::orderBy('name')->get();

        return view('teacher-portal.lesson-notes.create', compact(
            'teacher', 'subjects', 'classes', 'terms', 'current', 'strands', 'subStrands'
        ));
    }

    public function notesStore(Request $request): RedirectResponse
    {
        $teacher  = $this->authTeacher();
        $tenantId = app('currentTenant')?->id;

        $data = $this->validateNote($request);
        $data['tenant_id']  = $tenantId;
        $data['teacher_id'] = $teacher->id;

        if ($request->input('action') === 'submit') {
            $data['status']       = 'submitted';
            $data['submitted_at'] = now();
        } else {
            $data['status'] = 'draft';
        }

        $note = LessonNote::create($data);

        // Handle file attachments
        $this->handleAttachments($request, $note);

        $action = $data['status'] === 'submitted' ? 'submitted for approval' : 'saved as draft';
        return redirect()->route('teacher.portal.lesson-notes.show', $note->id)
            ->with('success', "Lesson note \"{$note->title}\" {$action}.");
    }

    public function notesShow(int $id): View
    {
        $teacher = $this->authTeacher();
        $note    = LessonNote::with([
            'schoolClass', 'subject', 'term.academicYear',
            'strand', 'subStrand', 'attachments', 'approvedBy',
        ])->where('teacher_id', $teacher->id)->findOrFail($id);

        return view('teacher-portal.lesson-notes.show', compact('note'));
    }

    public function notesEdit(int $id): View
    {
        $teacher = $this->authTeacher();
        $note    = LessonNote::where('teacher_id', $teacher->id)->findOrFail($id);

        if (! in_array($note->status, ['draft', 'revision_requested'])) {
            abort(403, 'Only draft or revision-requested notes can be edited.');
        }

        $subjects   = Subject::with('schoolClass')->where('teacher_id', $teacher->id)->orderBy('name')->get();
        $classes    = SchoolClass::whereHas('subjects', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->orWhere('class_teacher_id', $teacher->id)
            ->orderBy('name')
            ->get()
            ->unique('id');
        $terms      = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $strands    = CurriculumStrand::with('subStrands')->orderBy('name')->get();
        $subStrands = CurriculumSubStrand::orderBy('name')->get();

        return view('teacher-portal.lesson-notes.edit', compact(
            'note', 'subjects', 'classes', 'terms', 'strands', 'subStrands'
        ));
    }

    public function notesUpdate(Request $request, int $id): RedirectResponse
    {
        $teacher = $this->authTeacher();
        $note    = LessonNote::where('teacher_id', $teacher->id)->findOrFail($id);

        if (! in_array($note->status, ['draft', 'revision_requested'])) {
            abort(403, 'Only draft or revision-requested notes can be edited.');
        }

        $data = $this->validateNote($request);

        if ($request->input('action') === 'submit') {
            $data['status']          = 'submitted';
            $data['submitted_at']    = now();
            $data['revision_notes']  = null;
            $data['approved_at']     = null;
            $data['approved_by']     = null;
        }

        $note->update($data);

        $this->handleAttachments($request, $note);

        $action = ($data['status'] ?? $note->fresh()->status) === 'submitted' ? 'submitted for approval' : 'updated';
        return redirect()->route('teacher.portal.lesson-notes.show', $note->id)
            ->with('success', "Lesson note {$action}.");
    }

    public function notesSubmit(int $id): RedirectResponse
    {
        $teacher = $this->authTeacher();
        $note    = LessonNote::where('teacher_id', $teacher->id)->findOrFail($id);

        if (! in_array($note->status, ['draft', 'revision_requested'])) {
            return back()->with('error', 'This note cannot be submitted.');
        }

        $note->update([
            'status'         => 'submitted',
            'submitted_at'   => now(),
            'revision_notes' => null,
        ]);

        return redirect()->route('teacher.portal.lesson-notes.show', $note->id)
            ->with('success', 'Lesson note submitted for approval.');
    }

    public function notesDestroy(int $id): RedirectResponse
    {
        $teacher = $this->authTeacher();
        $note    = LessonNote::where('teacher_id', $teacher->id)->findOrFail($id);

        if ($note->status === 'approved') {
            return back()->with('error', 'Approved lesson notes cannot be deleted.');
        }

        $note->delete();
        return redirect()->route('teacher.portal.lesson-notes.index')
            ->with('success', 'Lesson note deleted.');
    }

    public function notesDeleteAttachment(int $noteId, int $attachmentId): RedirectResponse
    {
        $teacher = $this->authTeacher();
        LessonNote::where('teacher_id', $teacher->id)->findOrFail($noteId);

        $att = LessonNoteAttachment::where('lesson_note_id', $noteId)->findOrFail($attachmentId);
        $att->delete();

        return back()->with('success', 'Attachment removed.');
    }

    // ════════════════════════════════════════════════════════════════════════
    //  SCHEMES OF WORK
    // ════════════════════════════════════════════════════════════════════════

    public function schemesIndex(Request $request): View
    {
        $teacher = $this->authTeacher();
        $terms   = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $current = AcademicTerm::current() ?? $terms->first();

        $selectedTermId = $request->integer('term_id', $current?->id ?? 0);

        $schemes = SchemeOfWork::with(['schoolClass', 'subject', 'term'])
            ->where('teacher_id', $teacher->id)
            ->where('term_id', $selectedTermId)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('teacher-portal.schemes.index', compact('schemes', 'terms', 'selectedTermId'));
    }

    public function schemesCreate(): View
    {
        $teacher  = $this->authTeacher();
        $subjects = Subject::with('schoolClass')->where('teacher_id', $teacher->id)->orderBy('name')->get();
        $classes  = SchoolClass::whereHas('subjects', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->orWhere('class_teacher_id', $teacher->id)
            ->orderBy('name')
            ->get()
            ->unique('id');
        $terms    = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $current  = AcademicTerm::current();

        return view('teacher-portal.schemes.create', compact('subjects', 'classes', 'terms', 'current'));
    }

    public function schemesStore(Request $request): RedirectResponse
    {
        $teacher  = $this->authTeacher();
        $tenantId = app('currentTenant')?->id;

        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id'      => ['required', 'exists:subjects,id'],
            'term_id'         => ['required', 'exists:academic_terms,id'],
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:1000'],
            // Weekly rows
            'weeks'           => ['nullable', 'array'],
            'weeks.*.week_number'          => ['required', 'integer', 'min:1', 'max:20'],
            'weeks.*.week_ending'          => ['nullable', 'date'],
            'weeks.*.topic'                => ['required', 'string', 'max:255'],
            'weeks.*.learning_objectives'  => ['nullable', 'string'],
            'weeks.*.competencies'         => ['nullable', 'string', 'max:500'],
            'weeks.*.reference'            => ['nullable', 'string', 'max:500'],
        ]);

        $scheme = SchemeOfWork::create([
            'tenant_id'       => $tenantId,
            'teacher_id'      => $teacher->id,
            'school_class_id' => $data['school_class_id'],
            'subject_id'      => $data['subject_id'],
            'term_id'         => $data['term_id'],
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'status'          => 'active',
        ]);

        foreach ($data['weeks'] ?? [] as $week) {
            if (empty($week['topic'])) {
                continue;
            }
            $scheme->weeks()->create([
                'week_number'         => $week['week_number'],
                'week_ending'         => $week['week_ending'] ?? null,
                'topic'               => $week['topic'],
                'learning_objectives' => $week['learning_objectives'] ?? null,
                'competencies'        => $week['competencies'] ?? null,
                'reference'           => $week['reference'] ?? null,
                'is_completed'        => false,
            ]);
        }

        return redirect()->route('teacher.portal.schemes.show', $scheme->id)
            ->with('success', "Scheme of work \"{$scheme->title}\" created.");
    }

    public function schemesShow(int $id): View
    {
        $teacher = $this->authTeacher();
        $scheme  = SchemeOfWork::with(['schoolClass', 'subject', 'term.academicYear', 'weeks'])
            ->where('teacher_id', $teacher->id)
            ->findOrFail($id);

        return view('teacher-portal.schemes.show', compact('scheme'));
    }

    public function schemesEdit(int $id): View
    {
        $teacher  = $this->authTeacher();
        $scheme   = SchemeOfWork::with('weeks')
            ->where('teacher_id', $teacher->id)
            ->findOrFail($id);

        $subjects = Subject::with('schoolClass')->where('teacher_id', $teacher->id)->orderBy('name')->get();
        $classes  = SchoolClass::whereHas('subjects', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->orWhere('class_teacher_id', $teacher->id)
            ->orderBy('name')
            ->get()
            ->unique('id');
        $terms    = AcademicTerm::with('academicYear')->orderByDesc('id')->get();

        return view('teacher-portal.schemes.edit', compact('scheme', 'subjects', 'classes', 'terms'));
    }

    public function schemesUpdate(Request $request, int $id): RedirectResponse
    {
        $teacher = $this->authTeacher();
        $scheme  = SchemeOfWork::with('weeks')
            ->where('teacher_id', $teacher->id)
            ->findOrFail($id);

        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id'      => ['required', 'exists:subjects,id'],
            'term_id'         => ['required', 'exists:academic_terms,id'],
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'weeks'           => ['nullable', 'array'],
            'weeks.*.id'                   => ['nullable', 'integer'],
            'weeks.*.week_number'          => ['required', 'integer', 'min:1', 'max:20'],
            'weeks.*.week_ending'          => ['nullable', 'date'],
            'weeks.*.topic'                => ['required', 'string', 'max:255'],
            'weeks.*.learning_objectives'  => ['nullable', 'string'],
            'weeks.*.competencies'         => ['nullable', 'string', 'max:500'],
            'weeks.*.reference'            => ['nullable', 'string', 'max:500'],
            'weeks.*.is_completed'         => ['nullable', 'boolean'],
        ]);

        $scheme->update([
            'school_class_id' => $data['school_class_id'],
            'subject_id'      => $data['subject_id'],
            'term_id'         => $data['term_id'],
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
        ]);

        // Sync weeks: delete removed ones, upsert submitted ones
        $submittedIds = [];
        foreach ($data['weeks'] ?? [] as $week) {
            if (empty($week['topic'])) {
                continue;
            }
            $weekId = ! empty($week['id']) ? (int) $week['id'] : null;

            $attributes = [
                'week_number'         => $week['week_number'],
                'week_ending'         => $week['week_ending'] ?? null,
                'topic'               => $week['topic'],
                'learning_objectives' => $week['learning_objectives'] ?? null,
                'competencies'        => $week['competencies'] ?? null,
                'reference'           => $week['reference'] ?? null,
                'is_completed'        => ! empty($week['is_completed']),
            ];

            if ($weekId) {
                $existing = SchemeOfWorkWeek::where('scheme_of_work_id', $scheme->id)->find($weekId);
                if ($existing) {
                    $existing->update($attributes);
                    $submittedIds[] = $weekId;
                    continue;
                }
            }
            $newWeek = $scheme->weeks()->create($attributes);
            $submittedIds[] = $newWeek->id;
        }

        // Delete weeks that were not submitted
        $scheme->weeks()->whereNotIn('id', $submittedIds)->delete();

        return redirect()->route('teacher.portal.schemes.show', $scheme->id)
            ->with('success', 'Scheme of work updated.');
    }

    public function schemesDestroy(int $id): RedirectResponse
    {
        $teacher = $this->authTeacher();
        SchemeOfWork::where('teacher_id', $teacher->id)->findOrFail($id)->delete();

        return redirect()->route('teacher.portal.schemes.index')
            ->with('success', 'Scheme of work deleted.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validateNote(Request $request): array
    {
        return $request->validate([
            'school_class_id'      => ['required', 'exists:school_classes,id'],
            'subject_id'           => ['required', 'exists:subjects,id'],
            'term_id'              => ['required', 'exists:academic_terms,id'],
            'strand_id'            => ['nullable', 'exists:curriculum_strands,id'],
            'sub_strand_id'        => ['nullable', 'exists:curriculum_sub_strands,id'],
            'title'                => ['required', 'string', 'max:255'],
            'type'                 => ['required', 'in:lesson_note,lesson_plan'],
            'week_ending'          => ['nullable', 'date'],
            'lesson_date'          => ['nullable', 'date'],
            'day_of_week'          => ['nullable', 'in:Monday,Tuesday,Wednesday,Thursday,Friday'],
            'period'               => ['nullable', 'string', 'max:50'],
            'duration'             => ['nullable', 'integer', 'min:1', 'max:480'],
            'content_standard'     => ['nullable', 'string', 'max:500'],
            'indicator_code'       => ['nullable', 'string', 'max:100'],
            'indicator'            => ['nullable', 'string', 'max:1000'],
            'performance_indicator'=> ['nullable', 'string', 'max:1000'],
            'reference_materials'  => ['nullable', 'string', 'max:1000'],
            'tlr'                  => ['nullable', 'string', 'max:1000'],
            'core_competencies'    => ['nullable', 'array'],
            'core_competencies.*'  => ['string', 'in:cps,ci,cc,cg,pd,dl'],
            'keywords'             => ['nullable', 'string', 'max:500'],
            'starter'              => ['nullable', 'string'],
            'main_activities'      => ['required', 'string'],
            'assessment'           => ['nullable', 'string'],
            'conclusion'           => ['nullable', 'string'],
            'homework'             => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function handleAttachments(Request $request, LessonNote $note): void
    {
        $tenantId = app('currentTenant')?->id;

        // File uploads
        $uploadMap = [
            'attach_pdf'   => 'pdf',
            'attach_word'  => 'word',
            'attach_image' => 'image',
            'attach_audio' => 'audio',
            'attach_video' => 'video',
        ];

        foreach ($uploadMap as $field => $type) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                if (! $file || ! $file->isValid()) {
                    continue;
                }
                $path = $file->store("lesson-notes/{$note->id}", 'public');

                LessonNoteAttachment::create([
                    'tenant_id'     => $tenantId,
                    'lesson_note_id'=> $note->id,
                    'file_type'     => $type,
                    'original_name' => $file->getClientOriginalName(),
                    'file_path'     => $path,
                    'size_bytes'    => $file->getSize(),
                ]);
            }
        }

        // External link
        $linkUrl = (string) $request->input('attach_link', '');
        if ($linkUrl) {
            LessonNoteAttachment::create([
                'tenant_id'     => $tenantId,
                'lesson_note_id'=> $note->id,
                'file_type'     => 'link',
                'original_name' => $request->input('attach_link_name', 'External Link'),
                'url'           => $linkUrl,
            ]);
        }
    }
}
