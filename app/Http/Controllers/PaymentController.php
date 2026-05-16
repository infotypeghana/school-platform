<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use App\Notifications\SubscriptionExpiryNotification;
use App\Models\Subscription;
use App\Services\PaymentGatewayService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles subscription payments from the public pay/{slug} flow.
 *
 * Gateway HTTP logic lives in PaymentGatewayService — this controller only
 * orchestrates the payment lifecycle (create, redirect, verify, activate).
 */
class PaymentController extends Controller
{
    public function __construct(
        private PaymentGatewayService $gateway,
        private SubscriptionService   $subscriptionService,
    ) {}

    // ── Static payment landing page ───────────────────────────────────────────

    public function page(string $slug): \Illuminate\View\View
    {
        return view('payment.page', ['slug' => $slug]);
    }

    // ── Initiate payment ──────────────────────────────────────────────────────

    public function initiate(Request $request, string $slug)
    {
        $tenant       = Tenant::where('slug', $slug)->firstOrFail();
        $subscription = $this->subscriptionService->getCurrentSubscription($tenant);

        abort_unless($subscription !== null, 404, 'No active subscription found for this school.');

        // Deduplication — reuse an existing pending payment for the same subscription + gateway
        $gatewayName = $this->gateway->defaultGateway();

        $payment = Payment::where('subscription_id', $subscription->id)
            ->where('status', Payment::STATUS_PENDING)
            ->where('gateway', $gatewayName)
            ->latest()
            ->first();

        if (! $payment) {
            $payment = Payment::create([
                'tenant_id'       => $tenant->id,
                'subscription_id' => $subscription->id,
                'payment_type'    => Payment::TYPE_SUBSCRIPTION,  // ← always set
                'amount'          => $subscription->amount,
                'currency'        => config('billing.currency', 'GHS'),
                'gateway'         => $gatewayName,
                'reference'       => 'SMS-' . strtoupper(Str::random(12)),
                'status'          => Payment::STATUS_PENDING,
            ]);
        }

        $callbackUrl = $payment->gateway === Payment::GATEWAY_MOOLRE
            ? route('payment.callback.moolre')
            : route('payment.callback.paystack');

        $checkoutUrl = $this->gateway->checkoutUrl(
            $payment,
            $tenant,
            $callbackUrl,
            [
                'subscription_id' => $subscription->id,
                'cancel_action'   => route('payment.page', ['slug' => $tenant->slug]),
            ],
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

        if (! $reference) {
            return view('payment.callback', ['status' => 'error', 'message' => 'No payment reference provided.']);
        }

        $payment = Payment::where('reference', $reference)->with('tenant')->first();

        if (! $payment) {
            return view('payment.callback', ['status' => 'error', 'message' => 'Payment record not found.']);
        }

        // Already confirmed by webhook
        if ($payment->isSuccess()) {
            return view('payment.callback', [
                'status'  => 'success',
                'payment' => $payment,
                'tenant'  => $payment->tenant,
            ]);
        }

        // Webhook may not have arrived yet — verify directly with Paystack
        $verified = $this->gateway->verifyPaystack($reference);

        if ($verified && ($verified['data']['status'] ?? '') === 'success') {
            if (! $payment->isSuccess()) {
                $payment->update([
                    'status'              => Payment::STATUS_SUCCESS,
                    'gateway_reference'   => $verified['data']['id'] ?? null,
                    'paid_at'             => now(),
                    'webhook_received_at' => now(),
                    'metadata'            => $verified['data'],
                ]);
                $this->subscriptionService->activateFromPayment($payment);
            }

            return view('payment.callback', [
                'status'  => 'success',
                'payment' => $payment,
                'tenant'  => $payment->tenant,
            ]);
        }

        return view('payment.callback', [
            'status'  => 'pending',
            'payment' => $payment,
            'tenant'  => $payment->tenant,
        ]);
    }

    // ── Moolre browser callback ───────────────────────────────────────────────

    public function moolreCallback(Request $request)
    {
        $reference = $request->query('reference');

        if (! $reference) {
            return view('payment.callback', ['status' => 'error', 'message' => 'No payment reference provided.']);
        }

        $payment = Payment::where('reference', $reference)->with('tenant')->first();

        if (! $payment) {
            return view('payment.callback', ['status' => 'error', 'message' => 'Payment record not found.']);
        }

        if ($payment->isSuccess()) {
            return view('payment.callback', [
                'status'  => 'success',
                'payment' => $payment,
                'tenant'  => $payment->tenant,
            ]);
        }

        $verified = $this->gateway->verifyMoolre($reference);

        if ($verified) {
            if (! $payment->isSuccess()) {
                $payment->update([
                    'status'              => Payment::STATUS_SUCCESS,
                    'paid_at'             => now(),
                    'webhook_received_at' => now(),
                    'metadata'            => $verified,
                ]);
                $this->subscriptionService->activateFromPayment($payment);
            }

            return view('payment.callback', [
                'status'  => 'success',
                'payment' => $payment,
                'tenant'  => $payment->tenant,
            ]);
        }

        return view('payment.callback', [
            'status'  => 'pending',
            'payment' => $payment,
            'tenant'  => $payment->tenant,
        ]);
    }
}
