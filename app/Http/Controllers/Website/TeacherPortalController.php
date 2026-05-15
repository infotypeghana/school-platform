<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Services\GradeCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TeacherPortalController extends Controller
{
    // ── Authentication ────────────────────────────────────────────────────────

    public function showLogin(): View
    {
        return view('teacher-portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $tenant  = app('currentTenant');
        $teacher = Teacher::where('tenant_id', $tenant?->id)
            ->where('email', $request->email)
            ->where('portal_active', true)
            ->first();

        if (! $teacher || ! Hash::check($request->password, $teacher->portal_password)) {
            return back()
                ->withInput(['email' => $request->email])
                ->withErrors(['email' => 'Invalid email or password, or portal access is not enabled.']);
        }

        // Record login time
        $teacher->update(['portal_last_login' => now()]);

        session([
            'teacher_portal_id' => $teacher->id,
        ]);

        return redirect()->route('teacher.portal.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        session()->forget('teacher_portal_id');
        return redirect()->route('teacher.portal.login')
            ->with('success', 'You have been logged out.');
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard(): View
    {
        /** @var Teacher $teacher */
        $teacher     = view()->shared('authTeacher');
        $currentTerm = AcademicTerm::current();

        // Classes this teacher is responsible for (class teacher or subject teacher)
        $myClasses = SchoolClass::with(['students' => fn ($q) => $q->where('status', 'active')])
            ->where(function ($q) use ($teacher) {
                $q->where('class_teacher_id', $teacher->id)
                  ->orWhereHas('subjects', fn ($q2) => $q2->where('teacher_id', $teacher->id));
            })
            ->get()
            ->unique('id');

        // Recent announcements (active = published and not expired)
        $announcements = Announcement::active()
            ->whereIn('audience', ['all', 'teachers'])
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        return view('teacher-portal.dashboard', compact('teacher', 'currentTerm', 'myClasses', 'announcements'));
    }

    // ── Score Entry ───────────────────────────────────────────────────────────

    /**
     * Select class + term to enter scores.
     */
    public function scores(): View
    {
        /** @var Teacher $teacher */
        $teacher = view()->shared('authTeacher');

        // Only subjects assigned to this teacher
        $subjects = Subject::with('schoolClass')
            ->where('teacher_id', $teacher->id)
            ->get();

        $terms   = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $current = AcademicTerm::current();

        return view('teacher-portal.scores.index', compact('teacher', 'subjects', 'terms', 'current'));
    }

    /**
     * Score entry grid for a specific subject + term.
     */
    public function scoresEdit(Request $request): View
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'term_id'    => 'required|exists:academic_terms,id',
        ]);

        /** @var Teacher $teacher */
        $teacher = view()->shared('authTeacher');

        // Authorise: teacher must own this subject
        $subject = Subject::with('schoolClass.students')
            ->where('id', $request->subject_id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $term     = AcademicTerm::with('academicYear')->findOrFail($request->term_id);
        $class    = $subject->schoolClass;
        $students = $class?->students()->where('status', 'active')->orderBy('first_name')->get() ?? collect();

        // Pre-load existing assessments for this subject + term
        $existing = Assessment::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->get()
            ->keyBy('student_id');

        $tenant  = app()->bound('currentTenant') ? app('currentTenant') : null;
        $caMax   = GradeCalculator::tenantCaMax($tenant);
        $examMax = GradeCalculator::tenantExamMax($tenant);

        return view('teacher-portal.scores.edit', compact('teacher', 'subject', 'term', 'class', 'students', 'existing', 'caMax', 'examMax'));
    }

    /**
     * Save scores from the teacher's grid.
     */
    public function scoresUpdate(Request $request): RedirectResponse
    {
        $tenant  = app()->bound('currentTenant') ? app('currentTenant') : null;
        $caMax   = GradeCalculator::tenantCaMax($tenant);
        $examMax = GradeCalculator::tenantExamMax($tenant);

        $request->validate([
            'subject_id'      => 'required|exists:subjects,id',
            'term_id'         => 'required|exists:academic_terms,id',
            'scores'          => 'required|array',
            'scores.*.ca'     => "nullable|numeric|min:0|max:{$caMax}",
            'scores.*.exam'   => "nullable|numeric|min:0|max:{$examMax}",
        ]);

        /** @var Teacher $teacher */
        $teacher = view()->shared('authTeacher');

        // Authorise: teacher must own this subject
        $subject = Subject::where('id', $request->subject_id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $tenantId = app('currentTenant')?->id;

        // Security: only allow scores for students who belong to this subject's class.
        // The subject's school_class_id is already tenant-scoped via the ownership check above.
        $validStudentIds = \App\Models\Student::where('school_class_id', $subject->school_class_id)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->flip();

        foreach ($request->scores as $studentId => $score) {
            if (! isset($validStudentIds[(string) $studentId])) {
                continue; // submitted student does not belong to this class
            }
            $ca   = isset($score['ca'])   && $score['ca']   !== '' ? (float) $score['ca']   : null;
            $exam = isset($score['exam']) && $score['exam'] !== '' ? (float) $score['exam'] : null;

            if ($ca === null && $exam === null) {
                continue;
            }

            Assessment::updateOrCreate(
                [
                    'tenant_id'       => $tenantId,
                    'student_id'      => $studentId,
                    'subject_id'      => $subject->id,
                    'term_id'         => $request->term_id,
                    'school_class_id' => $subject->school_class_id,
                ],
                [
                    'ca_score'   => $ca   ?? 0,
                    'exam_score' => $exam ?? 0,
                ]
            );
        }

        return redirect()->route('teacher.portal.scores.edit', [
            'subject_id' => $subject->id,
            'term_id'    => $request->term_id,
        ])->with('success', 'Scores saved successfully.');
    }

    // ── Timetable ─────────────────────────────────────────────────────────────

    public function timetable(): View
    {
        /** @var Teacher $teacher */
        $teacher = view()->shared('authTeacher');

        // Timetable entries for this teacher (directly assigned or class teacher)
        $myClassIds = SchoolClass::where('class_teacher_id', $teacher->id)->pluck('id');

        $timetable = Timetable::with(['schoolClass', 'subject'])
            ->where(function ($q) use ($teacher, $myClassIds) {
                $q->where('teacher_id', $teacher->id)
                  ->orWhereIn('school_class_id', $myClassIds);
            })
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');   // keyed by integer 1–5

        // Ordered integer → name map
        $days = Timetable::DAYS; // [1=>'Monday', 2=>'Tuesday', ...]

        return view('teacher-portal.timetable', compact('teacher', 'timetable', 'days'));
    }
}
