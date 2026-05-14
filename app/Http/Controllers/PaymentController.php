<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    // ── Static payment landing page (shown before redirect to gateway) ──────────
    // Extracted from a closure so route:cache can serialize this route.
    public function page(string $slug): \Illuminate\View\View
    {
        return view('payment.page', ['slug' => $slug]);
    }

    // ── Initiate payment (redirect to gateway checkout) ───────────────────────
    public function initiate(Request $request, string $slug)
    {
        $tenant       = Tenant::where('slug', $slug)->firstOrFail();
        $subscription = $this->subscriptionService->getCurrentSubscription($tenant);

        abort_unless($subscription, 404, 'No active subscription found for this school.');

        // Don't create duplicate pending payments for the same subscription
        $payment = Payment::where('subscription_id', $subscription->id)
            ->where('status', Payment::STATUS_PENDING)
            ->where('gateway', $this->defaultGateway())
            ->latest()
            ->first();

        if (! $payment) {
            $payment = Payment::create([
                'tenant_id'       => $tenant->id,
                'subscription_id' => $subscription->id,
                'amount'          => $subscription->amount,
                'currency'        => 'GHS',
                'gateway'         => $this->defaultGateway(),
                'reference'       => 'SMS-' . strtoupper(Str::random(12)),
                'status'          => Payment::STATUS_PENDING,
            ]);
        }

        $checkoutUrl = match ($payment->gateway) {
            Payment::GATEWAY_MOOLRE => $this->initMoolre($payment, $tenant),
            default                 => $this->initPaystack($payment, $tenant),
        };

        if (! $checkoutUrl) {
            return back()->with('error', 'Could not initiate payment. Please try again later.');
        }

        return redirect()->away($checkoutUrl);
    }

    // ── Paystack callback (user returns after payment) ─────────────────────────
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

        // Webhook may not have fired yet — verify directly with Paystack API
        $verified = $this->verifyPaystackTransaction($reference);

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

    // ── Moolre callback (user returns after payment) ───────────────────────────
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

        // Already confirmed by webhook
        if ($payment->isSuccess()) {
            return view('payment.callback', [
                'status'  => 'success',
                'payment' => $payment,
                'tenant'  => $payment->tenant,
            ]);
        }

        // Verify directly with Moolre confirm endpoint
        $verified = $this->verifyMoolreTransaction($reference);

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

    // ── Moolre API initialization ─────────────────────────────────────────────
    private function initMoolre(Payment $payment, Tenant $tenant): ?string
    {
        $accountNumber = config('services.moolre.account_number');
        $publicKey     = config('services.moolre.public_key');
        $baseUrl       = config('services.moolre.base_url');

        if (! $accountNumber || ! $publicKey) {
            Log::error('Moolre credentials not configured');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Api-Pubkey' => $publicKey,
                'Accept'       => 'application/json',
            ])->post($baseUrl, [
                'state'         => 'starter',
                'accountnumber' => $accountNumber,
                'reference'     => $payment->reference,
                'email'         => $tenant->email,
                'amount'        => (string) $payment->amount,
                'currency'      => 'GHS',
                'callback'      => route('payment.callback.moolre') . '?reference=' . $payment->reference,
                'tx_source'     => 'schoolms',
                'nonce_value'   => Str::random(16),
            ]);

            if ($response->successful() && $response->json('status') == 1) {
                $authUrl = $response->json('data.authorization_url')
                    ?? $response->json('authorization_url');

                if ($authUrl) {
                    return $authUrl;
                }
            }

            Log::error('Moolre initialization failed', [
                'response' => $response->json(),
                'payment'  => $payment->reference,
            ]);
        } catch (\Throwable $e) {
            Log::error('Moolre HTTP error: ' . $e->getMessage());
        }

        return null;
    }

    // ── Paystack API initialization ───────────────────────────────────────────
    private function initPaystack(Payment $payment, Tenant $tenant): ?string
    {
        $secretKey = config('services.paystack.secret_key');

        if (! $secretKey) {
            Log::error('Paystack secret key not configured');
            return null;
        }

        try {
            $response = Http::withToken($secretKey)
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email'        => $tenant->email,
                    'amount'       => (int) ($payment->amount * 100), // convert GHS → pesewas
                    'currency'     => 'GHS',
                    'reference'    => $payment->reference,
                    'callback_url' => route('payment.callback.paystack'),
                    'metadata'     => [
                        'tenant_id'       => $tenant->id,
                        'subscription_id' => $payment->subscription_id,
                        'school_name'     => $tenant->name,
                        'cancel_action'   => route('payment.page', ['slug' => $tenant->slug]),
                    ],
                ]);

            if ($response->successful() && $response->json('status')) {
                return $response->json('data.authorization_url');
            }

            Log::error('Paystack initialization failed', [
                'response' => $response->json(),
                'payment'  => $payment->reference,
            ]);
        } catch (\Throwable $e) {
            Log::error('Paystack HTTP error: ' . $e->getMessage());
        }

        return null;
    }

    // ── Verify Paystack transaction server-side ───────────────────────────────
    private function verifyPaystackTransaction(string $reference): ?array
    {
        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->get("https://api.paystack.co/transaction/verify/{$reference}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error('Paystack verify error: ' . $e->getMessage());
        }

        return null;
    }

    // ── Verify Moolre transaction server-side ─────────────────────────────────
    private function verifyMoolreTransaction(string $reference): ?array
    {
        $accountNumber = config('services.moolre.account_number');
        $publicKey     = config('services.moolre.public_key');
        $baseUrl       = config('services.moolre.base_url');

        try {
            $response = Http::withHeaders([
                'X-Api-Pubkey' => $publicKey,
                'Accept'       => 'application/json',
            ])->post($baseUrl, [
                'state'         => 'confirm',
                'accountnumber' => $accountNumber,
                'reference'     => $reference,
            ]);

            if ($response->successful() && $response->json('status') == 1) {
                return $response->json('data') ?? $response->json();
            }
        } catch (\Throwable $e) {
            Log::error('Moolre verify error: ' . $e->getMessage());
        }

        return null;
    }

    private function defaultGateway(): string
    {
        return config('billing.default_gateway', Payment::GATEWAY_PAYSTACK);
    }
}
