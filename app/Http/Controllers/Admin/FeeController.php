<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\FeePaymentMail;
use App\Models\AcademicTerm;
use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\SmsService;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class FeeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Fee::with(['student.schoolClass', 'term.academicYear'])
            ->when($request->search, fn ($q) => $q->whereHas('student', fn ($q) => $q
                ->where('first_name', 'like', "%{$request->search}%")
                ->orWhere('last_name',  'like', "%{$request->search}%")
                ->orWhere('admission_number', 'like', "%{$request->search}%")
            ))
            ->when($request->status,   fn ($q) => $q->where('status', $request->status))
            ->when($request->term_id,  fn ($q) => $q->where('term_id', $request->term_id))
            ->when($request->class_id, fn ($q) => $q->whereHas('student', fn ($q) => $q->where('school_class_id', $request->class_id)))
            ->orderByDesc('created_at');

        $fees    = $query->paginate(30)->withQueryString();
        $terms   = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $classes = SchoolClass::orderBy('name')->get();

        // Summary stats — scoped to the same filters as the paginated list
        // Clone the builder BEFORE paginate() consumes it so aggregates respect
        // the active term / class / status / search filters.
        $totalDue   = (clone $query)->sum('amount');
        $totalPaid  = (clone $query)->sum('amount_paid');
        $totalOwed  = (clone $query)->sum('balance');

        return view('admin.fees.index', compact('fees', 'terms', 'classes', 'totalDue', 'totalPaid', 'totalOwed'));
    }

    public function create(): View
    {
        $students = Student::with('schoolClass')->where('status', 'active')->orderBy('first_name')->get();
        $terms    = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $current  = AcademicTerm::current();

        return view('admin.fees.create', compact('students', 'terms', 'current'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = app('currentTenant')->id;
        $data = $request->validate([
            // Tenant-scoped: prevents assigning a fee to a student from another school.
            'student_id'     => ['required', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'term_id'        => 'required|exists:academic_terms,id',
            'fee_type'       => 'required|string|max:100',
            'amount'         => 'required|numeric|min:0.01',
            'amount_paid'    => 'nullable|numeric|min:0',
            'due_date'       => 'nullable|date',
            'receipt_number' => 'nullable|string|max:100',
        ]);

        $data['amount_paid'] = $data['amount_paid'] ?? 0;

        Fee::create($data);

        return redirect()->route('admin.fees')
            ->with('success', 'Fee record created.');
    }

    public function edit(Fee $fee): View
    {
        $this->authorize('update', $fee);
        $students = Student::with('schoolClass')->where('status', 'active')->orderBy('first_name')->get();
        $terms    = AcademicTerm::with('academicYear')->orderByDesc('id')->get();

        return view('admin.fees.edit', compact('fee', 'students', 'terms'));
    }

    public function update(Request $request, Fee $fee): RedirectResponse
    {
        $this->authorize('update', $fee);
        $tenantId = app('currentTenant')->id;
        $data = $request->validate([
            'student_id'     => ['required', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'term_id'        => 'required|exists:academic_terms,id',
            'fee_type'       => 'required|string|max:100',
            'amount'         => 'required|numeric|min:0.01',
            'amount_paid'    => 'required|numeric|min:0',
            'due_date'       => 'nullable|date',
            'receipt_number' => 'nullable|string|max:100',
        ]);

        $fee->update($data);

        return redirect()->route('admin.fees')
            ->with('success', 'Fee record updated.');
    }

    /**
     * Quick-record a payment against an existing fee.
     * Auto-assigns a receipt number the first time a payment is recorded.
     */
    public function recordPayment(Request $request, Fee $fee): RedirectResponse
    {
        $this->authorize('update', $fee);
        $request->validate(['payment_amount' => 'required|numeric|min:0.01']);

        $updates = [
            'amount_paid' => min($fee->amount, $fee->amount_paid + (float) $request->payment_amount),
        ];

        // Auto-assign receipt number on first payment
        if (! $fee->receipt_number) {
            $updates['receipt_number'] = 'RCT-' . now()->format('Ymd') . '-' . str_pad((string) $fee->id, 5, '0', STR_PAD_LEFT);
        }

        $fee->update($updates);
        $fresh = $fee->fresh(['student', 'term']);

        // Email receipt to guardian
        $guardianEmail = $fresh->student?->guardian_email;
        if ($guardianEmail) {
            try {
                Mail::to($guardianEmail)
                    ->queue(new FeePaymentMail($fresh, (float) $request->payment_amount));
            } catch (\Throwable $e) {
                Log::error('FeePaymentMail failed', ['fee_id' => $fee->id, 'error' => $e->getMessage()]);
            }
        }

        // SMS notification to guardian
        $guardian = $fresh->student?->guardian_phone;
        if ($guardian) {
            $studentName = $fresh->student->full_name;
            $paid        = number_format($request->payment_amount, 2);
            $balance     = number_format($fresh->balance, 2);
            $school      = app('currentTenant')?->name ?? 'School';

            $body = $fresh->balance <= 0
                ? "Dear Parent/Guardian, GHS {$paid} payment received for {$studentName} ({$fresh->fee_type}). Account is now FULLY PAID. Receipt: {$fresh->receipt_number}. Thank you — {$school}."
                : "Dear Parent/Guardian, GHS {$paid} payment received for {$studentName} ({$fresh->fee_type}). Outstanding balance: GHS {$balance}. Receipt: {$fresh->receipt_number}. — {$school}.";

            app(SmsService::class)->send($guardian, $body, app('currentTenant'), $fresh);
        }

        return back()->with('success', sprintf(
            'Payment of GHS %s recorded. Balance: GHS %s. Receipt: %s.',
            number_format($request->payment_amount, 2),
            number_format($fresh->balance, 2),
            $fresh->receipt_number
        ));
    }

    /**
     * Download a PDF receipt for a fee record.
     */
    public function receipt(Fee $fee)
    {
        $this->authorize('view', $fee);
        $fee->load(['student.schoolClass', 'term.academicYear']);
        $tenant = app('currentTenant');

        // Ensure receipt number exists
        if (! $fee->receipt_number) {
            $fee->update([
                'receipt_number' => 'RCT-' . now()->format('Ymd') . '-' . str_pad((string) $fee->id, 5, '0', STR_PAD_LEFT),
            ]);
            $fee->refresh();
        }

        $pdf = Pdf::loadView('admin.fees.receipt', compact('fee', 'tenant'))
            ->setPaper('a5', 'portrait');

        return $pdf->download("receipt-{$fee->receipt_number}.pdf");
    }

    public function destroy(Fee $fee): RedirectResponse
    {
        $this->authorize('delete', $fee);
        $fee->delete();
        return redirect()->route('admin.fees')->with('success', 'Fee record deleted.');
    }

    // ── Bulk fee assignment ───────────────────────────────────────────────────

    /**
     * Show the bulk-assign form (one class → all active students).
     */
    public function bulkCreate(): View
    {
        $classes = SchoolClass::orderBy('name')->get();
        $terms   = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $current = AcademicTerm::current();

        return view('admin.fees.bulk', compact('classes', 'terms', 'current'));
    }

    /**
     * Create a fee record for every active student in the chosen class,
     * skipping any student who already has a record for the same term + fee_type.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'term_id'         => 'required|exists:academic_terms,id',
            'fee_type'        => 'required|string|max:100',
            'amount'          => 'required|numeric|min:0.01',
            'amount_paid'     => 'nullable|numeric|min:0',
            'due_date'        => 'nullable|date',
        ]);

        $students = Student::where('school_class_id', $data['school_class_id'])
            ->where('status', 'active')
            ->get();

        if ($students->isEmpty()) {
            return back()->with('error', 'No active students found in the selected class.');
        }

        $created = 0;
        $skipped = 0;

        foreach ($students as $student) {
            // Idempotent: skip if this student already has the same fee this term
            if (Fee::where('student_id', $student->id)
                   ->where('term_id',    $data['term_id'])
                   ->where('fee_type',   $data['fee_type'])
                   ->exists()) {
                $skipped++;
                continue;
            }

            Fee::create([
                'student_id'  => $student->id,
                'term_id'     => $data['term_id'],
                'fee_type'    => $data['fee_type'],
                'amount'      => $data['amount'],
                'amount_paid' => $data['amount_paid'] ?? 0,
                'due_date'    => $data['due_date'] ?? null,
            ]);

            $created++;
        }

        $message = "{$created} fee record(s) created.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (duplicate for this term and fee type).";
        }

        return redirect()->route('admin.fees')->with('success', $message);
    }

    /**
     * Fee summary: per-class breakdown for a given term.
     */
    public function summary(Request $request): View
    {
        $terms   = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $current = AcademicTerm::current();
        $termId  = $request->input('term_id', $current?->id);
        $term    = $termId ? AcademicTerm::with('academicYear')->find($termId) : null;

        // Per-class aggregates for the selected term
        $classes = SchoolClass::withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get();

        $summary = [];
        $grandTotal   = 0;
        $grandPaid    = 0;
        $grandBalance = 0;

        foreach ($classes as $class) {
            // Get all student IDs in this class
            $studentIds = $class->students()->where('status', 'active')->pluck('id');

            $query = Fee::whereIn('student_id', $studentIds);
            if ($termId) {
                $query->where('term_id', $termId);
            }

            // Use single-quoted string literals — MySQL with ANSI_QUOTES off treats
            // double-quoted identifiers as column names, not strings, returning 0.
            $totals = $query->selectRaw("
                COUNT(*) as fee_count,
                SUM(amount) as total_levied,
                SUM(amount_paid) as total_paid,
                SUM(balance) as total_balance,
                SUM(CASE WHEN status = 'paid'    THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                SUM(CASE WHEN status = 'unpaid'  THEN 1 ELSE 0 END) as unpaid_count
            ")->first();

            if (! $totals || $totals->fee_count == 0) {
                continue; // skip classes with no fee records this term
            }

            $summary[] = [
                'class'         => $class,
                'fee_count'     => (int)   $totals->fee_count,
                'total_levied'  => (float) $totals->total_levied,
                'total_paid'    => (float) $totals->total_paid,
                'total_balance' => (float) $totals->total_balance,
                'paid_count'    => (int)   $totals->paid_count,
                'partial_count' => (int)   $totals->partial_count,
                'unpaid_count'  => (int)   $totals->unpaid_count,
                'collection_rate' => $totals->total_levied > 0
                    ? round(($totals->total_paid / $totals->total_levied) * 100, 1)
                    : 0,
            ];

            $grandTotal   += $totals->total_levied;
            $grandPaid    += $totals->total_paid;
            $grandBalance += $totals->total_balance;
        }

        return view('admin.fees.summary', compact(
            'terms', 'term', 'termId', 'summary',
            'grandTotal', 'grandPaid', 'grandBalance'
        ));
    }
}
