<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\FeePaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MoolreWebhookController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private FeePaymentService   $feePaymentService,
    ) {}

    public function __invoke(Request $request): Response
    {
        // ── 1. Signature verification (HMAC-SHA256 with public key) ──────────
        $publicKey = (string) config('services.moolre.public_key', '');
        $signature = $request->header('X-Moolre-Signature') ?? '';
        $rawBody   = $request->getContent();

        if (! $publicKey || ! $signature) {
            Log::warning('Moolre webhook: missing signature or key', ['ip' => $request->ip()]);
            return response('Unauthorized', 401);
        }

        if (! hash_equals(hash_hmac('sha256', $rawBody, $publicKey), $signature)) {
            Log::warning('Moolre webhook: invalid signature', ['ip' => $request->ip()]);
            return response('Unauthorized', 401);
        }

        // ── 2. Parse payload ──────────────────────────────────────────────────
        $payload   = $request->json()->all();
        $status    = $payload['status'] ?? null;
        $data      = $payload['data']   ?? [];
        $reference = $data['reference'] ?? null;

        Log::info('Moolre webhook received', ['status' => $status]);

        if ($status !== 1 || ! $reference) {
            return response('OK', 200);
        }

        // ── 3. Find payment record ────────────────────────────────────────────
        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            Log::warning('Moolre webhook: payment not found', compact('reference'));
            return response('OK', 200);
        }

        // ── 4. Idempotency guard ──────────────────────────────────────────────
        if ($payment->isSuccess()) {
            Log::info('Moolre webhook: already processed', compact('reference'));
            return response('OK', 200);
        }

        // ── 5. Amount sanity check (subscription payments only) ───────────────
        // For fee payments the amount can legitimately be a partial top-up,
        // so we only enforce the floor for subscription-type payments.
        $paidAmount = (float) ($data['amount'] ?? 0);
        if (
            $payment->payment_type !== Payment::TYPE_FEE
            && $paidAmount > 0
            && $paidAmount < $payment->amount
        ) {
            Log::warning('Moolre webhook: underpayment', [
                'expected'  => $payment->amount,
                'received'  => $paidAmount,
                'reference' => $reference,
            ]);
            $payment->update([
                'status'   => Payment::STATUS_FAILED,
                'metadata' => array_merge((array) $payment->metadata, [
                    'rejection' => 'Underpayment',
                    'data'      => $data,
                ]),
            ]);
            return response('OK', 200);
        }

        // ── 6. Route by payment type ──────────────────────────────────────────
        if ($payment->payment_type === Payment::TYPE_FEE) {
            $this->feePaymentService->confirm($payment, $data);

            Log::info('Moolre webhook: fee payment confirmed', [
                'tenant_id'  => $payment->tenant_id,
                'reference'  => $reference,
            ]);
        } else {
            $payment->update([
                'status'              => Payment::STATUS_SUCCESS,
                'metadata'            => $data,
                'paid_at'             => now(),
                'webhook_received_at' => now(),
            ]);

            $this->subscriptionService->activateFromPayment($payment);

            Log::info('Moolre webhook: subscription activated', [
                'tenant_id' => $payment->tenant_id,
                'reference' => $reference,
            ]);
        }

        return response('OK', 200);
    }
}
