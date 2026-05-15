<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Tenant;
use App\Notifications\SubscriptionExpiryNotification;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    public function handle(Request $request): Response
    {
        // 1. Verify Paystack HMAC signature — non-negotiable
        if (! $this->verifySignature($request)) {
            Log::warning('Paystack webhook: invalid signature', [
                'ip' => $request->ip(),
            ]);
            return response('Unauthorized', 401);
        }

        $event   = $request->input('event');
        $data    = $request->input('data', []);
        $reference = $data['reference'] ?? null;

        if ($event !== 'charge.success' || ! $reference) {
            return response('OK', 200);
        }

        // 2. Idempotency — ignore duplicate webhooks
        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            Log::warning('Paystack webhook: unknown reference', compact('reference'));
            return response('OK', 200);
        }

        if ($payment->isSuccess()) {
            Log::info('Paystack webhook: already processed', compact('reference'));
            return response('OK', 200);
        }

        // 3. Mark payment successful
        $payment->update([
            'status'              => Payment::STATUS_SUCCESS,
            'gateway_reference'   => $data['id'] ?? null,
            'paid_at'             => now(),
            'webhook_received_at' => now(),
            'metadata'            => $data,
        ]);

        // 4. Activate subscription instantly
        $this->subscriptionService->activateFromPayment($payment);

        // 5. Send confirmation notification
        try {
            $tenant       = $payment->tenant;
            $subscription = $payment->subscription;
            if ($tenant && $subscription) {
                $tenant->notify(new SubscriptionExpiryNotification($subscription, 'payment_confirmed'));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send payment confirmation: ' . $e->getMessage());
        }

        Log::info('Paystack webhook: subscription activated', [
            'reference'       => $reference,
            'tenant_id'       => $payment->tenant_id,
            'subscription_id' => $payment->subscription_id,
        ]);

        return response('OK', 200);
    }

    private function verifySignature(Request $request): bool
    {
        $secret    = (string) config('services.paystack.secret_key', '');
        $signature = $request->header('X-Paystack-Signature') ?? '';
        $expected  = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
