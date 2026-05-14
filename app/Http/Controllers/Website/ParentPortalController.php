<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentPortalController extends Controller
{
    /**
     * Show the lookup / login form.
     * If already authenticated, redirect straight to dashboard.
     */
    public function index(): View|RedirectResponse
    {
        if (session()->has('parent_portal_student_id')) {
            return redirect()->route('website.portal.dashboard');
        }

        return view('website.portal.lookup');
    }

    /**
     * Authenticate via admission number + date of birth.
     * On success: store student ID in session and redirect to dashboard.
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

        // Store in session (per tenant — sessions are isolated by subdomain)
        session(['parent_portal_student_id' => $student->id]);

        return redirect()->route('website.portal.dashboard');
    }

    /**
     * Show the student dashboard (session-authenticated).
     */
    public function dashboard(): View|RedirectResponse
    {
        $studentId = session('parent_portal_student_id');

        if (! $studentId) {
            return redirect()->route('website.portal')
                ->withErrors(['admission_number' => 'Your session has expired. Please log in again.']);
        }

        $student = Student::with([
            'schoolClass',
            'fees.term',
            'attendances.term',
            'assessments.subject',
            'assessments.term',
        ])->find($studentId);

        // Student may have been deleted or transferred to another tenant
        if (! $student) {
            session()->forget('parent_portal_student_id');
            return redirect()->route('website.portal')
                ->withErrors(['admission_number' => 'Student record not found. Please log in again.']);
        }

        $currentTerm = AcademicTerm::current();

        // Current term fees
        $currentFees = $currentTerm
            ? $student->fees->where('term_id', $currentTerm->id)
            : collect();

        // All fees grouped by term
        $allFees = $student->fees->groupBy('term_id');

        // Attendance summary (all-time)
        $attendances = $student->attendances;
        $totalAttendance = $attendances->count();
        $attendanceSummary = $totalAttendance > 0 ? [
            'present' => $attendances->where('status', 'present')->count(),
            'absent'  => $attendances->where('status', 'absent')->count(),
            'late'    => $attendances->where('status', 'late')->count(),
            'total'   => $totalAttendance,
            'rate'    => round(
                ($attendances->whereIn('status', ['present', 'late'])->count() / $totalAttendance) * 100
            ),
        ] : null;

        // Assessments grouped by term
        $assessmentsByTerm = $student->assessments
            ->sortBy('subject.name')
            ->groupBy('term_id');

        return view('website.portal.student', compact(
            'student', 'currentTerm', 'currentFees', 'allFees',
            'attendanceSummary', 'assessmentsByTerm'
        ));
    }

    /**
     * Log out — clear the session and redirect to the login form.
     */
    public function logout(Request $request): RedirectResponse
    {
        session()->forget('parent_portal_student_id');
        return redirect()->route('website.portal')
            ->with('success', 'You have been logged out successfully.');
    }
}
