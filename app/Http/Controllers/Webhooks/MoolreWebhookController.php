<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MoolreWebhookController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    public function __invoke(Request $request): Response
    {
        // ── 1. Signature verification ──────────────────────────────────────
        // Moolre signs the raw payload with HMAC-SHA256 using the public key
        $publicKey = (string) config('services.moolre.public_key', '');
        $signature = $request->header('X-Moolre-Signature') ?? '';
        $rawBody   = $request->getContent();

        if (! $publicKey || ! $signature) {
            Log::warning('Moolre webhook: missing signature or key', [
                'ip' => $request->ip(),
            ]);
            return response('Unauthorized', 401);
        }

        $expected = hash_hmac('sha256', $rawBody, $publicKey);

        if (! hash_equals($expected, $signature)) {
            Log::warning('Moolre webhook: invalid signature', [
                'ip' => $request->ip(),
            ]);
            return response('Unauthorized', 401);
        }

        // ── 2. Parse payload ───────────────────────────────────────────────
        $payload = $request->json()->all();
        $status  = $payload['status'] ?? null;
        $data    = $payload['data']   ?? [];

        Log::info('Moolre webhook received', ['status' => $status]);

        // status 1 = success
        if ($status != 1) {
            return response('OK', 200);
        }

        $reference = $data['reference'] ?? null;

        if (! $reference) {
            Log::warning('Moolre webhook: no reference in payload');
            return response('OK', 200);
        }

        // ── 3. Find payment record ─────────────────────────────────────────
        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            Log::warning('Moolre webhook: payment not found', ['reference' => $reference]);
            return response('OK', 200);
        }

        // ── 4. Idempotency guard ───────────────────────────────────────────
        if ($payment->isSuccess()) {
            Log::info('Moolre webhook: already processed', ['reference' => $reference]);
            return response('OK', 200);
        }

        // ── 5. Verify amount matches ───────────────────────────────────────
        $paidAmount = (float) ($data['amount'] ?? 0);
        if ($paidAmount > 0 && $paidAmount < $payment->amount) {
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

        // ── 6. Mark paid + activate subscription ──────────────────────────
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

        return response('OK', 200);
    }
}
