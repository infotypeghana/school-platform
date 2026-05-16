<?php

namespace App\Http\Controllers;

use App\Models\Fee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\FeePaymentService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Handles online fee payment from the parent portal.
 *
 * Flow:
 *  1. Parent selects an unpaid fee  → POST /portal/fees/{fee}/pay
 *  2. Controller creates Payment record, calls gateway, redirects parent
 *  3. Parent returns               → GET  /payment/fee/callback/{gateway}
 *  4. Controller delegates to FeePaymentService::confirm() (locked transaction)
 *
 * All gateway HTTP logic lives in PaymentGatewayService.
 * All fee credit + receipt logic lives in FeePaymentService.
 */
class FeePaymentController extends Controller
{
    public function __construct(
        private PaymentGatewayService $gateway,
        private FeePaymentService     $feePaymentService,
    ) {}

    // ── Initiate fee payment ──────────────────────────────────────────────────

    public function initiate(Request $request, Fee $fee)
    {
        // Verify this fee belongs to the student in the parent portal session
        $studentId = session('parent_portal_student_id');
        if (! $studentId || $fee->student_id !== (int) $studentId) {
            abort(403);
        }

        if ($fee->status === 'paid') {
            return back()->with('info', 'This fee is already fully paid.');
        }

        $tenant  = app('currentTenant');
        $student = Student::findOrFail($studentId);

        // Deduplication — reuse an existing pending payment for the same fee
        $payment = Payment::where('fee_id', $fee->id)
            ->where('status', Payment::STATUS_PENDING)
            ->latest()
            ->first();

        if (! $payment) {
            $payment = Payment::create([
                'tenant_id'    => $tenant->id,
                'fee_id'       => $fee->id,
                'payment_type' => Payment::TYPE_FEE,
                'amount'       => $fee->balance,
                'currency'     => config('billing.currency', 'GHS'),
                'gateway'      => $this->gateway->defaultGateway(),
                'reference'    => 'FEE-' . strtoupper(Str::random(12)),
                'status'       => Payment::STATUS_PENDING,
                'metadata'     => [
                    'student_id'   => $student->id,
                    'student_name' => $student->full_name,
                    'fee_type'     => $fee->fee_type,
                ],
            ]);
        }

        // Fee callbacks are served on the school subdomain, so pass the slug
        $callbackUrl = $payment->gateway === Payment::GATEWAY_MOOLRE
            ? route('fee.payment.callback.moolre', ['slug' => $tenant->slug])
            : route('fee.payment.callback.paystack', ['slug' => $tenant->slug]);

        $checkoutUrl = $this->gateway->checkoutUrl(
            payment:       $payment,
            tenant:        $tenant,
            callbackUrl:   $callbackUrl,
            paystackMeta:  ['student_name' => $student->full_name, 'fee_id' => $fee->id],
            moolreTxSource: 'schoolms-fee',
        );

        if (! $checkoutUrl) {
            return back()->with('error', 'Could not initiate payment. Please try again later.');
        }

        return redirect()->away($checkoutUrl);
    }

    // ── Paystack browser callback ─────────────────────────────────────────────

    public function paystackCallback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');

        $payment = Payment::where('reference', $reference)
            ->where('payment_type', Payment::TYPE_FEE)
            ->with(['fee.student', 'tenant'])
            ->first();

        if (! $payment) {
            return view('portal.fee-payment-result', ['status' => 'error']);
        }

        // Already confirmed (by webhook or a prior callback)
        if ($payment->isSuccess()) {
            return view('portal.fee-payment-result', ['status' => 'success', 'payment' => $payment]);
        }

        $verified = $this->gateway->verifyPaystack($reference);

        if ($verified && ($verified['data']['status'] ?? '') === 'success') {
            $this->feePaymentService->confirm($payment, $verified['data'] ?? []);
            return view('portal.fee-payment-result', ['status' => 'success', 'payment' => $payment->fresh()]);
        }

        return view('portal.fee-payment-result', ['status' => 'pending', 'payment' => $payment]);
    }

    // ── Moolre browser callback ───────────────────────────────────────────────

    public function moolreCallback(Request $request)
    {
        $reference = $request->query('reference');

        $payment = Payment::where('reference', $reference)
            ->where('payment_type', Payment::TYPE_FEE)
            ->with(['fee.student', 'tenant'])
            ->first();

        if (! $payment) {
            return view('portal.fee-payment-result', ['status' => 'error']);
        }

        if ($payment->isSuccess()) {
            return view('portal.fee-payment-result', ['status' => 'success', 'payment' => $payment]);
        }

        $verified = $this->gateway->verifyMoolre($reference);

        if ($verified) {
            $this->feePaymentService->confirm($payment, $verified);
            return view('portal.fee-payment-result', ['status' => 'success', 'payment' => $payment->fresh()]);
        }

        return view('portal.fee-payment-result', ['status' => 'pending', 'payment' => $payment]);
    }
}
