<?php

namespace App\Services;

use App\Mail\FeePaymentMail;
use App\Models\Fee;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Handles fee-payment confirmation atomically.
 *
 * Extracted from FeePaymentController so the same logic can be called from:
 *   - The browser callback (parent returns from gateway)
 *   - The Paystack / Moolre webhook (server-to-server, fires even if browser closes)
 *
 * Both paths call FeePaymentService::confirm() — a single write path that is:
 *   • Idempotent   — re-entrant call after prior success is a no-op
 *   • Race-safe    — pessimistic locks on payment + fee prevent double-credit
 *   • Complete     — queues both email receipt and SMS in the same transaction
 */
class FeePaymentService
{
    public function __construct(private SmsService $smsService) {}

    /**
     * Confirm a fee payment: update statuses, credit the fee, send receipts.
     *
     * MUST be called outside an existing DB transaction — this method opens its
     * own transaction so the locks are scoped correctly.
     *
     * @param  array<string, mixed>  $gatewayData  Raw payload from the gateway.
     */
    public function confirm(Payment $payment, array $gatewayData): void
    {
        DB::transaction(function () use ($payment, $gatewayData): void {

            // ── Re-read with an exclusive row lock ────────────────────────────
            // Prevents two concurrent callbacks (browser + webhook) from both
            // entering the credit path and doubling the amount_paid value.
            $payment = Payment::lockForUpdate()->find($payment->id);

            if (! $payment) {
                Log::warning('FeePaymentService: payment not found', ['id' => $payment?->id]);
                return;
            }

            // ── Idempotency guard ─────────────────────────────────────────────
            if ($payment->isSuccess()) {
                return; // already processed — webhook or callback fired twice
            }

            // ── Mark payment successful ───────────────────────────────────────
            $payment->update([
                'status'              => Payment::STATUS_SUCCESS,
                'paid_at'             => now(),
                'webhook_received_at' => now(),
                'metadata'            => array_merge($payment->metadata ?? [], $gatewayData),
            ]);

            // ── Credit the fee (with its own row lock) ────────────────────────
            if (! $payment->fee_id) {
                Log::warning('FeePaymentService: payment has no fee_id', ['payment_id' => $payment->id]);
                return;
            }

            $fee = Fee::lockForUpdate()->find($payment->fee_id);

            if (! $fee) {
                Log::warning('FeePaymentService: fee not found', ['fee_id' => $payment->fee_id]);
                return;
            }

            $fee->update([
                'amount_paid' => $fee->amount_paid + $payment->amount,
                // Fee::booted() automatically recalculates balance + status on save
            ]);

            $fee->refresh();

            // ── Receipts (queued — don't hold the DB lock) ────────────────────
            $student = $fee->student;

            if ($student?->guardian_email) {
                try {
                    Mail::to($student->guardian_email)
                        ->queue(new FeePaymentMail($fee, (float) $payment->amount));
                } catch (\Throwable $e) {
                    Log::error('FeePaymentService: FeePaymentMail failed', [
                        'payment_id' => $payment->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $phone = $student?->guardian_phone;
            if ($phone && $student) {
                try {
                    $this->smsService->notifyFeePayment(
                        $phone,
                        $student->full_name,
                        (float) $payment->amount,
                        (float) $fee->balance,
                        $payment->tenant?->name ?? config('app.name'),
                        $payment->tenant,
                        $fee,
                    );
                } catch (\Throwable $e) {
                    Log::error('FeePaymentService: SMS receipt failed', [
                        'payment_id' => $payment->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

        }); // end DB::transaction
    }
}
