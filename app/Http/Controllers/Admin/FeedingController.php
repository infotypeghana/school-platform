<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\FeedingConfig;
use App\Models\FeedingFee;
use App\Models\FeedingPayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FeedingFeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedingController extends Controller
{
    public function __construct(private readonly FeedingFeeService $service) {}

    // ── Configuration ─────────────────────────────────────────────────────────

    /**
     * Show the feeding fee configuration page.
     * Lists: school-wide config + per-class overrides.
     */
    public function config(): View
    {
        $schoolWide = FeedingConfig::whereNull('school_class_id')->first();
        $classConfigs = FeedingConfig::whereNotNull('school_class_id')
            ->with('schoolClass')
            ->get()
            ->keyBy('school_class_id');

        $classes = SchoolClass::orderBy('name')->get();

        return view('admin.feeding.config', compact('schoolWide', 'classConfigs', 'classes'));
    }

    /**
     * Save school-wide default config.
     */
    public function updateConfig(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rate_per_day'         => ['required', 'numeric', 'min:0'],
            'billing_mode'         => ['required', 'in:daily,weekly,monthly,termly'],
            'school_days_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'is_active'            => ['boolean'],
            'notes'                => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        FeedingConfig::updateOrCreate(
            ['school_class_id' => null],
            $data
        );

        return redirect()->route('admin.feeding.config')
            ->with('success', 'School-wide feeding fee configuration saved.');
    }

    /**
     * Save (or delete) a per-class config override.
     */
    public function updateClassConfig(Request $request, int $classId): RedirectResponse
    {
        $class = SchoolClass::findOrFail($classId);

        // "Remove override" button sends _remove
        if ($request->boolean('_remove')) {
            FeedingConfig::where('school_class_id', $class->id)->delete();

            return redirect()->route('admin.feeding.config')
                ->with('success', "Override removed for {$class->name}. School-wide rate will apply.");
        }

        $data = $request->validate([
            'rate_per_day'         => ['required', 'numeric', 'min:0'],
            'billing_mode'         => ['required', 'in:daily,weekly,monthly,termly'],
            'school_days_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'is_active'            => ['boolean'],
            'notes'                => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        FeedingConfig::updateOrCreate(
            ['school_class_id' => $class->id],
            $data
        );

        return redirect()->route('admin.feeding.config')
            ->with('success', "Feeding fee override saved for {$class->name}.");
    }

    // ── Fee Index ─────────────────────────────────────────────────────────────

    /**
     * List feeding fees, filterable by term and class.
     */
    public function index(Request $request): View
    {
        $terms   = AcademicTerm::orderByDesc('id')->get();
        $classes = SchoolClass::orderBy('name')->get();

        $currentTerm = AcademicTerm::current() ?? $terms->first();

        $selectedTermId  = $request->integer('term_id', $currentTerm?->id ?? 0);
        $selectedClassId = $request->integer('class_id');
        $selectedStatus  = $request->string('status');

        $query = FeedingFee::with(['student', 'schoolClass', 'term'])
            ->where('term_id', $selectedTermId);

        if ($selectedClassId) {
            $query->where('school_class_id', $selectedClassId);
        }

        if ($selectedStatus) {
            $query->where('status', $selectedStatus);
        }

        $fees  = $query->orderBy('school_class_id')->orderBy('student_id')->paginate(50)->withQueryString();
        $stats = $selectedTermId ? $this->service->termStats($selectedTermId) : null;

        return view('admin.feeding.index', compact(
            'fees', 'terms', 'classes', 'stats',
            'selectedTermId', 'selectedClassId', 'selectedStatus'
        ));
    }

    // ── Bulk Assign ───────────────────────────────────────────────────────────

    /**
     * Show the bulk-assign form.
     */
    public function assignForm(): View
    {
        $classes = SchoolClass::orderBy('name')->get();
        $terms   = AcademicTerm::orderByDesc('id')->get();

        return view('admin.feeding.assign', compact('classes', 'terms'));
    }

    /**
     * Bulk-assign feeding fees for a class + term.
     */
    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id'     => ['required', 'integer', 'exists:school_classes,id'],
            'term_id'      => ['required', 'integer', 'exists:academic_terms,id'],
            'feeding_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $class = SchoolClass::findOrFail($data['class_id']);
        $term  = AcademicTerm::findOrFail($data['term_id']);

        try {
            $result = $this->service->assignToClass($class, $term, $data['feeding_days']);

            $msg = "Assigned feeding fees: {$result['assigned']} new";
            if ($result['skipped']) {
                $msg .= ", {$result['skipped']} already existed (skipped).";
            } else {
                $msg .= '.';
            }

            return redirect()->route('admin.feeding.index', ['term_id' => $term->id, 'class_id' => $class->id])
                ->with('success', $msg);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ── Individual Fee ────────────────────────────────────────────────────────

    /**
     * Show a single student's feeding fee detail + payment history.
     */
    public function show(int $id): View
    {
        $fee = FeedingFee::with(['student.schoolClass', 'term.academicYear', 'payments.recordedBy'])
            ->findOrFail($id);

        return view('admin.feeding.show', compact('fee'));
    }

    // ── Record Payment ────────────────────────────────────────────────────────

    public function pay(Request $request, int $id): RedirectResponse
    {
        $fee = FeedingFee::findOrFail($id);

        $data = $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_date'   => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank_transfer,cheque'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payment = $this->service->recordPayment(
                fee: $fee,
                amount: (float) $data['amount'],
                paymentDate: $data['payment_date'],
                paymentMethod: $data['payment_method'],
                recordedBy: auth()->id(),
                notes: $data['notes'] ?? null,
            );

            return redirect()->route('admin.feeding.show', $fee->id)
                ->with('success', "Payment of GHS " . number_format($data['amount'], 2) . " recorded. Receipt: {$payment->receipt_number}");
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ── Exempt / Un-exempt ────────────────────────────────────────────────────

    public function exempt(Request $request, int $id): RedirectResponse
    {
        $fee  = FeedingFee::findOrFail($id);
        $data = $request->validate([
            'exemption_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $fee->is_exempt        = ! $fee->is_exempt;
        $fee->exemption_reason = $fee->is_exempt ? ($data['exemption_reason'] ?? null) : null;
        $fee->save();

        $msg = $fee->is_exempt
            ? 'Student marked as exempt from feeding fees.'
            : 'Exemption removed. Feeding fee is now active.';

        return redirect()->route('admin.feeding.show', $fee->id)->with('success', $msg);
    }

    // ── Report ────────────────────────────────────────────────────────────────

    public function report(Request $request): View
    {
        $terms       = AcademicTerm::orderByDesc('id')->get();
        $currentTerm = AcademicTerm::current() ?? $terms->first();
        $selectedTermId = $request->integer('term_id', $currentTerm?->id ?? 0);

        $stats     = $selectedTermId ? $this->service->termStats($selectedTermId) : null;
        $breakdown = $selectedTermId ? $this->service->classBreakdown($selectedTermId) : collect();
        $term      = $selectedTermId ? AcademicTerm::find($selectedTermId) : null;

        // Recent payments for the selected term (last 50)
        $recentPayments = FeedingPayment::with(['student', 'feedingFee.schoolClass'])
            ->where('term_id', $selectedTermId)
            ->orderByDesc('payment_date')
            ->limit(50)
            ->get();

        return view('admin.feeding.report', compact(
            'terms', 'stats', 'breakdown', 'term', 'selectedTermId', 'recentPayments'
        ));
    }

    // ── Receipt ───────────────────────────────────────────────────────────────

    public function receipt(int $paymentId): View
    {
        $payment = FeedingPayment::with([
            'student.schoolClass',
            'feedingFee.term.academicYear',
            'recordedBy',
        ])->findOrFail($paymentId);

        $tenant = app('currentTenant');

        return view('admin.feeding.receipt', compact('payment', 'tenant'));
    }
}
