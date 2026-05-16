<?php

namespace App\Http\Controllers;

use App\Models\Fee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles online fee payment from the parent portal.
 *
 * Flow:
 *  1. Parent selects an unpaid fee → POST /portal/fees/{fee}/pay
 *  2. Controller creates a Payment record, calls gateway, redirects parent
 *  3. Parent returns → GET /payment/fee/callback/{gateway}
 *  4. Controller verifies, marks fee paid, sends SMS receipt
 */
class FeePaymentController extends Controller
{
    public function __construct(private SmsService $smsService) {}

    // ── Initiate fee payment ──────────────────────────────────────────────────

    public function initiate(Request $request, string $slug, Fee $fee)
    {
        // Verify this fee belongs to the student in session
        $studentId = session('parent_portal_student_id');
        if (! $studentId || $fee->student_id !== (int) $studentId) {
            abort(403);
        }

        if ($fee->status === 'paid') {
            return back()->with('info', 'This fee is already fully paid.');
        }

        $tenant  = app('currentTenant');
        $student = Student::findOrFail($studentId);

        // Prevent duplicate pending payments for the same fee
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
                'currency'     => 'GHS',
                'gateway'      => $this->defaultGateway(),
                'reference'    => 'FEE-' . strtoupper(Str::random(12)),
                'status'       => Payment::STATUS_PENDING,
                'metadata'     => [
                    'student_id'   => $student->id,
                    'student_name' => $student->full_name,
                    'fee_type'     => $fee->fee_type,
                ],
            ]);
        }

        $checkoutUrl = match ($payment->gateway) {
            Payment::GATEWAY_MOOLRE => $this->initMoolre($payment, $tenant, $student->full_name),
            default                 => $this->initPaystack($payment, $tenant, $student->full_name),
        };

        if (! $checkoutUrl) {
            return back()->with('error', 'Could not initiate payment. Please try again later.');
        }

        return redirect()->away($checkoutUrl);
    }

    // ── Paystack callback ─────────────────────────────────────────────────────

    public function paystackCallback(Request $request, string $slug)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');
        $payment   = Payment::where('reference', $reference)
            ->where('payment_type', Payment::TYPE_FEE)
            ->with(['fee.student', 'tenant'])
            ->first();

        if (! $payment) {
            return view('portal.fee-payment-result', ['status' => 'error']);
        }

        if ($payment->isSuccess()) {
            return view('portal.fee-payment-result', ['status' => 'success', 'payment' => $payment]);
        }

        $verified = $this->verifyPaystack($reference);

        if ($verified && ($verified['data']['status'] ?? '') === 'success') {
            $this->confirmFeePayment($payment, $verified['data']);
            return view('portal.fee-payment-result', ['status' => 'success', 'payment' => $payment->fresh()]);
        }

        return view('portal.fee-payment-result', ['status' => 'pending', 'payment' => $payment]);
    }

    // ── Moolre callback ───────────────────────────────────────────────────────

    public function moolreCallback(Request $request, string $slug)
    {
        $reference = $request->query('reference');
        $payment   = Payment::where('reference', $reference)
            ->where('payment_type', Payment::TYPE_FEE)
            ->with(['fee.student', 'tenant'])
            ->first();

        if (! $payment) {
            return view('portal.fee-payment-result', ['status' => 'error']);
        }

        if ($payment->isSuccess()) {
            return view('portal.fee-payment-result', ['status' => 'success', 'payment' => $payment]);
        }

        $verified = $this->verifyMoolre($reference);

        if ($verified) {
            $this->confirmFeePayment($payment, $verified);
            return view('portal.fee-payment-result', ['status' => 'success', 'payment' => $payment->fresh()]);
        }

        return view('portal.fee-payment-result', ['status' => 'pending', 'payment' => $payment]);
    }

    // ── Shared confirmation logic ─────────────────────────────────────────────

    private function confirmFeePayment(Payment $payment, array $gatewayData): void
    {
        $payment->update([
            'status'              => Payment::STATUS_SUCCESS,
            'paid_at'             => now(),
            'webhook_received_at' => now(),
            'metadata'            => array_merge($payment->metadata ?? [], $gatewayData),
        ]);

        $fee = $payment->fee;

        if ($fee) {
            $fee->update([
                'amount_paid' => $fee->amount_paid + $payment->amount,
            ]);
            // Fee::booted() recalculates balance + status automatically

            // SMS receipt to guardian
            $student = $fee->student;
            $phone   = $student?->guardian_phone ?? $student?->guardian_contact;
            if ($phone) {
                $this->smsService->notifyFeePayment(
                    $phone,
                    $student->full_name,
                    $payment->amount,
                    $fee->fresh()->balance,
                    $payment->tenant?->name ?? 'Your School',
                    $payment->tenant,
                    $fee,
                );
            }
        }
    }

    // ── Gateway helpers ───────────────────────────────────────────────────────

    private function initPaystack(Payment $payment, Tenant $tenant, string $studentName): ?string
    {
        $secretKey = config('services.paystack.secret_key');

        try {
            $response = Http::withToken($secretKey)
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email'        => $tenant->contact_email ?? $tenant->email,
                    'amount'       => (int) ($payment->amount * 100),
                    'currency'     => 'GHS',
                    'reference'    => $payment->reference,
                    'callback_url' => route('fee.payment.callback.paystack', ['slug' => $tenant->slug]),
                    'metadata'     => [
                        'student_name' => $studentName,
                        'fee_id'       => $payment->fee_id,
                        'tenant_id'    => $tenant->id,
                    ],
                ]);

            if ($response->successful() && $response->json('status')) {
                return $response->json('data.authorization_url');
            }

            Log::error('Paystack fee payment init failed', ['response' => $response->json()]);
        } catch (\Throwable $e) {
            Log::error('Paystack fee HTTP error: ' . $e->getMessage());
        }

        return null;
    }

    private function initMoolre(Payment $payment, Tenant $tenant, string $studentName): ?string
    {
        try {
            $response = Http::withHeaders([
                'X-Api-Pubkey' => config('services.moolre.public_key'),
                'Accept'       => 'application/json',
            ])->post(config('services.moolre.base_url'), [
                'state'         => 'starter',
                'accountnumber' => config('services.moolre.account_number'),
                'reference'     => $payment->reference,
                'email'         => $tenant->contact_email ?? $tenant->email,
                'amount'        => (string) $payment->amount,
                'currency'      => 'GHS',
                'callback'      => route('fee.payment.callback.moolre', ['slug' => $tenant->slug])
                                   . '?reference=' . $payment->reference,
                'tx_source'     => 'schoolms-fee',
                'nonce_value'   => Str::random(16),
            ]);

            if ($response->successful() && $response->json('status') == 1) {
                return $response->json('data.authorization_url') ?? $response->json('authorization_url');
            }

            Log::error('Moolre fee payment init failed', ['response' => $response->json()]);
        } catch (\Throwable $e) {
            Log::error('Moolre fee HTTP error: ' . $e->getMessage());
        }

        return null;
    }

    private function verifyPaystack(string $reference): ?array
    {
        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->get("https://api.paystack.co/transaction/verify/{$reference}");
            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error('Paystack fee verify error: ' . $e->getMessage());
            return null;
        }
    }

    private function verifyMoolre(string $reference): ?array
    {
        try {
            $response = Http::withHeaders(['X-Api-Pubkey' => config('services.moolre.public_key'), 'Accept' => 'application/json'])
                ->post(config('services.moolre.base_url'), [
                    'state'         => 'confirm',
                    'accountnumber' => config('services.moolre.account_number'),
                    'reference'     => $reference,
                ]);
            return ($response->successful() && $response->json('status') == 1)
                ? ($response->json('data') ?? $response->json())
                : null;
        } catch (\Throwable $e) {
            Log::error('Moolre fee verify error: ' . $e->getMessage());
            return null;
        }
    }

    private function defaultGateway(): string
    {
        return config('billing.default_gateway', Payment::GATEWAY_PAYSTACK);
    }
}
