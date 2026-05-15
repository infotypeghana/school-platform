<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\FeedingFee;
use App\Models\Student;
use App\Services\GradeCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Parent Portal — session-based, multi-ward aware.
 *
 * Session keys (all namespaced to avoid collision with other portals):
 *   parent_portal_student_ids  → array<int>  all wards the parent has added
 *   parent_portal_active_id    → int          the ward currently being viewed
 */
class ParentPortalController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    /** All ward IDs stored in session for this parent. */
    private function wardIds(): array
    {
        return session('parent_portal_student_ids', []);
    }

    /** Currently viewed ward ID, defaulting to the first in the list. */
    private function activeId(): ?int
    {
        $ids = $this->wardIds();
        if (empty($ids)) {
            return null;
        }
        $active = session('parent_portal_active_id');
        return in_array($active, $ids, true) ? $active : $ids[0];
    }

    /** Whether any ward session exists. */
    private function hasSession(): bool
    {
        return ! empty($this->wardIds());
    }

    // ── Login / Lookup ────────────────────────────────────────────────────────

    /**
     * Show the lookup / login form.
     * If already authenticated, redirect to dashboard.
     */
    public function index(): View|RedirectResponse
    {
        if ($this->hasSession()) {
            return redirect()->route('website.portal.dashboard');
        }
        return view('website.portal.lookup');
    }

    /**
     * Authenticate a ward via admission number + date of birth.
     *
     * - First time:  stores the student ID and sets it as the active ward.
     * - Subsequent:  adds the student to the existing list (deduplicates).
     *
     * Accessed from both the unauthenticated login form AND the "Add another
     * child" flow from the dashboard, so it handles both states gracefully.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $request->validate([
            'admission_number' => 'required|string|max:50',
            'date_of_birth'    => 'required|date',
        ]);

        $student = Student::where('admission_number', $request->admission_number)
            ->whereDate('date_of_birth', $request->date_of_birth)
            ->first();

        if (! $student) {
            return back()
                ->withInput()
                ->withErrors(['admission_number' => 'No student found with those details. Please check the admission number and date of birth.']);
        }

        $ids = $this->wardIds();

        // Guard: prevent adding a ward that already belongs to a different session
        // (i.e. the parent is trying to add a student from another family).
        // We simply allow it — the only check is admission_number + DOB matching.

        if (! in_array($student->id, $ids, true)) {
            $ids[] = $student->id;
            session(['parent_portal_student_ids' => $ids]);
        }

        // Always switch to the newly-added (or re-confirmed) ward
        session(['parent_portal_active_id' => $student->id]);

        return redirect()->route('website.portal.dashboard')
            ->with('success', in_array($student->id, $this->wardIds(), true) && count($this->wardIds()) > 1
                ? "Switched to {$student->first_name}'s records."
                : null);
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    /**
     * Show the currently-active ward's records.
     */
    public function dashboard(): View|RedirectResponse
    {
        if (! $this->hasSession()) {
            return redirect()->route('website.portal')
                ->withErrors(['admission_number' => 'Your session has expired. Please log in again.']);
        }

        $activeId = $this->activeId();

        $student = Student::with([
            'schoolClass',
            'fees.term',
            'attendances.term',
            'assessments.subject',
            'assessments.term',
        ])->find($activeId);

        // Student may have been deleted or deactivated
        if (! $student) {
            // Remove the stale ID and re-check
            $ids = array_values(array_filter($this->wardIds(), fn ($id) => $id !== $activeId));
            session([
                'parent_portal_student_ids' => $ids,
                'parent_portal_active_id'   => $ids[0] ?? null,
            ]);

            if (empty($ids)) {
                session()->forget(['parent_portal_student_ids', 'parent_portal_active_id']);
                return redirect()->route('website.portal')
                    ->withErrors(['admission_number' => 'Student record not found. Please log in again.']);
            }

            return redirect()->route('website.portal.dashboard');
        }

        // Load all wards for the switcher — only the basic info needed
        $allWards = Student::with('schoolClass')
            ->whereIn('id', $this->wardIds())
            ->get()
            ->keyBy('id');

        $currentTerm = AcademicTerm::current();

        $currentFees = $currentTerm
            ? $student->fees->where('term_id', $currentTerm->id)
            : collect();

        // Feeding fee for current term
        $currentFeedingFee = null;
        if ($currentTerm) {
            $ff = FeedingFee::where('student_id', $student->id)
                ->where('term_id', $currentTerm->id)
                ->first();
            if ($ff) {
                $currentFeedingFee = $ff->is_exempt ? 'exempt' : $ff;
            }
        }

        $allFees = $student->fees->groupBy('term_id');

        $attendances      = $student->attendances;
        $totalAttendance  = $attendances->count();
        $attendanceSummary = $totalAttendance > 0 ? [
            'present' => $attendances->where('status', 'present')->count(),
            'absent'  => $attendances->where('status', 'absent')->count(),
            'late'    => $attendances->where('status', 'late')->count(),
            'total'   => $totalAttendance,
            'rate'    => round(
                ($attendances->whereIn('status', ['present', 'late'])->count() / $totalAttendance) * 100
            ),
        ] : null;

        $assessmentsByTerm = $student->assessments
            ->sortBy('subject.name')
            ->groupBy('term_id');

        $tenant  = app()->bound('currentTenant') ? app('currentTenant') : null;
        $caMax   = GradeCalculator::tenantCaMax($tenant);
        $examMax = GradeCalculator::tenantExamMax($tenant);

        return view('website.portal.student', compact(
            'student',
            'allWards',
            'currentTerm',
            'currentFees',
            'allFees',
            'attendanceSummary',
            'assessmentsByTerm',
            'caMax',
            'examMax',
            'currentFeedingFee',
        ));
    }

    // ── Ward switching ────────────────────────────────────────────────────────

    /**
     * Switch the active ward without re-authenticating.
     * The target ID must already be in the session list.
     */
    public function switchWard(Request $request): RedirectResponse
    {
        $request->validate(['student_id' => 'required|integer']);
        $targetId = (int) $request->student_id;

        if (! in_array($targetId, $this->wardIds(), true)) {
            return back()->withErrors(['student_id' => 'That student is not linked to your session.']);
        }

        session(['parent_portal_active_id' => $targetId]);

        return redirect()->route('website.portal.dashboard');
    }

    /**
     * Show the "add another child" form.
     * Redirects to login page if not yet authenticated.
     */
    public function addWardForm(): View|RedirectResponse
    {
        if (! $this->hasSession()) {
            return redirect()->route('website.portal');
        }
        return view('website.portal.add-ward');
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    /**
     * Log out completely — clear all ward sessions.
     */
    public function logout(Request $request): RedirectResponse
    {
        session()->forget(['parent_portal_student_ids', 'parent_portal_active_id']);
        return redirect()->route('website.portal')
            ->with('success', 'You have been logged out successfully.');
    }
}
