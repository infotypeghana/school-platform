<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StudentEnrolledMail;
use App\Models\Admission;
use App\Models\AcademicTerm;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdmissionController extends Controller
{
    // ── Index: list all applications with filter/search ──────────────────────
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Admission::class);
        $status = $request->input('status', 'all');     // all | pending | accepted | rejected
        $search = $request->input('search', '');
        $termId = $request->input('term_id');

        $terms   = AcademicTerm::orderByDesc('start_date')->get();
        $current = AcademicTerm::where('is_current', true)->first();

        $query = Admission::query()
            ->with('term')
            ->orderByDesc('submitted_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($termId) {
            $query->where('term_id', $termId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name',  'like', "%{$search}%")
                  ->orWhere('guardian_name',  'like', "%{$search}%")
                  ->orWhere('guardian_phone', 'like', "%{$search}%")
                  ->orWhere('class_applying_for', 'like', "%{$search}%");
            });
        }

        $admissions = $query->paginate(25)->withQueryString();

        // Counts for status tabs
        $counts = [
            'all'      => Admission::count(),
            'pending'  => Admission::where('status', 'pending')->count(),
            'accepted' => Admission::where('status', 'accepted')->count(),
            'rejected' => Admission::where('status', 'rejected')->count(),
            'enrolled' => Admission::where('status', 'enrolled')->count(),
        ];

        return view('admin.admissions.index', compact(
            'admissions', 'terms', 'current', 'status', 'search', 'termId', 'counts'
        ));
    }

    // ── Show: full application detail ─────────────────────────────────────────
    public function show(Admission $admission): View
    {
        $this->authorize('view', $admission);
        $admission->load('term');
        return view('admin.admissions.show', compact('admission'));
    }

    // ── Accept ────────────────────────────────────────────────────────────────
    public function accept(Request $request, Admission $admission): RedirectResponse
    {
        $this->authorize('update', $admission);
        if ($admission->status !== 'pending') {
            return back()->with('error', 'Only pending applications can be accepted.');
        }

        $admission->update(['status' => 'accepted']);

        return redirect()
            ->route('admin.admissions.index')
            ->with('success', "Application for {$admission->full_name} has been accepted.");
    }

    // ── Reject ────────────────────────────────────────────────────────────────
    public function reject(Request $request, Admission $admission): RedirectResponse
    {
        $this->authorize('update', $admission);
        if ($admission->status !== 'pending') {
            return back()->with('error', 'Only pending applications can be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $admission->update([
            'status' => 'rejected',
            'notes'  => $validated['rejection_reason'] ?? $admission->notes,
        ]);

        return redirect()
            ->route('admin.admissions.index')
            ->with('success', "Application for {$admission->full_name} has been rejected.");
    }

    // ── Bulk status update ────────────────────────────────────────────────────
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Admission::class);

        $tenantId = app('currentTenant')->id;
        $validated = $request->validate([
            'ids'    => 'required|array',
            // Tenant-scoped exists: prevents a crafted POST from bulk-acting on
            // another tenant's applications (raw exists: bypasses HasTenantScope).
            'ids.*'  => ['integer', Rule::exists('admissions', 'id')->where('tenant_id', $tenantId)],
            'action' => 'required|in:accepted,rejected',
        ]);

        Admission::whereIn('id', $validated['ids'])
            ->where('status', 'pending')
            ->update(['status' => $validated['action']]);

        $count  = count($validated['ids']);
        $action = $validated['action'];

        return back()->with('success', "{$count} application(s) marked as {$action}.");
    }

    // ── Enrol: show pre-filled student form ──────────────────────────────────

    public function enrollForm(Admission $admission): RedirectResponse|View
    {
        $this->authorize('update', $admission);
        // Idempotency: already enrolled — send straight to the student record
        if ($admission->isEnrolled()) {
            return redirect()
                ->route('admin.students.show', $admission->student_id)
                ->with('info', "{$admission->full_name} is already enrolled as a student.");
        }

        // Guard: can only enrol accepted applications
        if ($admission->status !== 'accepted') {
            return back()->with('error', 'Only accepted applications can be enrolled.');
        }

        $classes = SchoolClass::orderBy('name')->get();

        return view('admin.admissions.enroll', compact('admission', 'classes'));
    }

    // ── Enrol: create the student record ──────────────────────────────────────

    public function enroll(Request $request, Admission $admission): RedirectResponse
    {
        $this->authorize('update', $admission);
        // Idempotency
        if ($admission->isEnrolled()) {
            return redirect()
                ->route('admin.students.show', $admission->student_id)
                ->with('info', "{$admission->full_name} is already enrolled.");
        }

        // Guard
        if ($admission->status !== 'accepted') {
            return back()->with('error', 'Only accepted applications can be enrolled.');
        }

        $data = $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'school_class_id' => 'required|exists:school_classes,id',
            'date_of_birth'   => 'nullable|date|before:today',
            'gender'          => 'nullable|in:male,female',
            'guardian_name'   => 'nullable|string|max:150',
            'guardian_phone'  => 'nullable|string|max:20',
            'guardian_email'  => 'nullable|email|max:100',
            'address'         => 'nullable|string|max:255',
            'admission_date'  => 'nullable|date',
        ]);

        // Create the student — admission_number auto-generated by Student::boot()
        $student = Student::create(array_merge($data, [
            'tenant_id' => $admission->tenant_id,
            'status'    => 'active',
        ]));

        // Link the admission to the new student and mark as enrolled
        $admission->update([
            'student_id'  => $student->id,
            'status'      => 'enrolled',
            'enrolled_at' => now(),
        ]);

        // Send confirmation email to guardian
        $guardianEmail = $student->guardian_email;
        if ($guardianEmail) {
            $student->load('schoolClass');
            $tenant = app('currentTenant');
            try {
                Mail::to($guardianEmail)->queue(new StudentEnrolledMail($student, $tenant));
            } catch (\Throwable $e) {
                Log::error('StudentEnrolledMail failed', [
                    'student_id' => $student->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', "{$student->full_name} has been enrolled successfully. Admission number: {$student->admission_number}");
    }

    // ── Destroy ───────────────────────────────────────────────────────────────
    public function destroy(Admission $admission): RedirectResponse
    {
        $this->authorize('delete', $admission);
        $name = $admission->full_name;
        $admission->delete();

        return redirect()
            ->route('admin.admissions.index')
            ->with('success', "Application for {$name} has been deleted.");
    }
}
