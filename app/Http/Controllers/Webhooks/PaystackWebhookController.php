<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionExpiryNotification;
use App\Services\FeePaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    // Maximum age (seconds) for a webhook delivery before we reject it as a replay
    private const MAX_DELIVERY_AGE_SECONDS = 300;

    public function __construct(
        private SubscriptionService $subscriptionService,
        private FeePaymentService   $feePaymentService,
    ) {}

    public function handle(Request $request): Response
    {
        // ── 1. Verify HMAC-SHA512 signature — non-negotiable ─────────────────
        if (! $this->verifySignature($request)) {
            Log::warning('Paystack webhook: invalid signature', ['ip' => $request->ip()]);
            return response('Unauthorized', 401);
        }

        // ── 2. Replay-attack prevention ───────────────────────────────────────
        // Paystack does not send a canonical timestamp header, so we derive
        // freshness from the created_at field inside the payload.
        $data      = $request->input('data', []);
        $createdAt = $data['created_at'] ?? null;

        if ($createdAt && ! $this->isPayloadFresh($createdAt)) {
            Log::warning('Paystack webhook: stale payload rejected', [
                'created_at' => $createdAt,
                'ip'         => $request->ip(),
            ]);
            return response('Stale payload', 400);
        }

        // ── 3. Deduplicate by idempotency key ─────────────────────────────────
        $idempotencyKey = 'webhook:paystack:' . ($data['reference'] ?? md5($request->getContent()));
        if (! Cache::add($idempotencyKey, 1, now()->addMinutes(30))) {
            Log::info('Paystack webhook: duplicate delivery suppressed', ['key' => $idempotencyKey]);
            return response('OK', 200);
        }

        $event     = $request->input('event');
        $reference = $data['reference'] ?? null;

        if ($event !== 'charge.success' || ! $reference) {
            return response('OK', 200);
        }

        // ── 4. Find payment record ────────────────────────────────────────────
        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            Log::warning('Paystack webhook: unknown reference', compact('reference'));
            return response('OK', 200);
        }

        // ── 5. Idempotency guard ──────────────────────────────────────────────
        if ($payment->isSuccess()) {
            Log::info('Paystack webhook: already processed', compact('reference'));
            return response('OK', 200);
        }

        // ── 6. Route by payment type ──────────────────────────────────────────
        if ($payment->payment_type === Payment::TYPE_FEE) {
            $this->handleFeePayment($payment, $data);
        } else {
            $this->handleSubscriptionPayment($payment, $data);
        }

        return response('OK', 200);
    }

    // ── Fee payment path ──────────────────────────────────────────────────────

    private function handleFeePayment(Payment $payment, array $data): void
    {
        $this->feePaymentService->confirm($payment, $data);

        Log::info('Paystack webhook: fee payment confirmed', [
            'reference'  => $payment->reference,
            'payment_id' => $payment->id,
            'tenant_id'  => $payment->tenant_id,
        ]);
    }

    // ── Subscription payment path ─────────────────────────────────────────────

    private function handleSubscriptionPayment(Payment $payment, array $data): void
    {
        $payment->update([
            'status'              => Payment::STATUS_SUCCESS,
            'gateway_reference'   => $data['id'] ?? null,
            'paid_at'             => now(),
            'webhook_received_at' => now(),
            'metadata'            => $data,
        ]);

        $this->subscriptionService->activateFromPayment($payment);

        // Send payment-confirmed notification to school admin
        try {
            $tenant       = $payment->tenant;
            $subscription = $payment->subscription;
            if ($tenant instanceof Tenant && $subscription instanceof Subscription) {
                $tenant->notify(new SubscriptionExpiryNotification($subscription, 'payment_confirmed'));
            }
        } catch (\Throwable $e) {
            Log::error('Paystack webhook: payment confirmation notification failed', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Paystack webhook: subscription activated', [
            'reference'       => $payment->reference,
            'tenant_id'       => $payment->tenant_id,
            'subscription_id' => $payment->subscription_id,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function verifySignature(Request $request): bool
    {
        $secret    = (string) config('services.paystack.secret_key', '');
        $signature = $request->header('X-Paystack-Signature') ?? '';
        $expected  = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    private function isPayloadFresh(int|string $createdAt): bool
    {
        $ts = is_int($createdAt) ? $createdAt : strtotime((string) $createdAt);
        if (! $ts) {
            return true; // If we can't parse, don't block the delivery
        }
        return (time() - $ts) <= self::MAX_DELIVERY_AGE_SECONDS;
    }
}
