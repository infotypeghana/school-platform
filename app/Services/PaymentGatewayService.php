<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Centralises all outbound HTTP calls to Paystack and Moolre.
 *
 * Previously duplicated across PaymentController (subscription payments) and
 * FeePaymentController (fee payments).  A single source of truth means gateway
 * API changes are fixed once, sandbox/live switching is controlled via config,
 * and every call is logged consistently.
 *
 * Usage:
 *   $url = app(PaymentGatewayService::class)->initPaystack($payment, $tenant, $callbackUrl, $metadata);
 */
class PaymentGatewayService
{
    // ── Initialise ────────────────────────────────────────────────────────────

    /**
     * Initialise a Paystack transaction and return the authorization_url.
     *
     * @param  array<string, mixed>  $metadata   Extra metadata forwarded to Paystack.
     * @return string|null           Checkout URL, or null on failure.
     */
    public function initPaystack(
        Payment $payment,
        Tenant  $tenant,
        string  $callbackUrl,
        array   $metadata = [],
    ): ?string {
        $secretKey = config('services.paystack.secret_key');
        $baseUrl   = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');

        if (! $secretKey) {
            Log::error('PaymentGatewayService: Paystack secret key not configured');
            return null;
        }

        $email = $tenant->contact_email ?: $tenant->email;

        try {
            $response = Http::withToken($secretKey)
                ->timeout(15)
                ->post("{$baseUrl}/transaction/initialize", [
                    'email'        => $email,
                    'amount'       => (int) ($payment->amount * 100), // GHS → pesewas
                    'currency'     => config('billing.currency', 'GHS'),
                    'reference'    => $payment->reference,
                    'callback_url' => $callbackUrl,
                    'metadata'     => array_merge([
                        'tenant_id'   => $tenant->id,
                        'school_name' => $tenant->name,
                        'payment_ref' => $payment->reference,
                    ], $metadata),
                ]);

            if ($response->successful() && $response->json('status')) {
                return $response->json('data.authorization_url');
            }

            Log::error('PaymentGatewayService: Paystack init failed', [
                'reference' => $payment->reference,
                'response'  => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('PaymentGatewayService: Paystack HTTP error', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Initialise a Moolre transaction and return the authorization_url.
     *
     * @param  string  $txSource  Moolre tx_source tag (e.g. 'schoolms', 'schoolms-fee').
     * @return string|null        Checkout URL, or null on failure.
     */
    public function initMoolre(
        Payment $payment,
        Tenant  $tenant,
        string  $callbackUrl,
        string  $txSource = 'schoolms',
    ): ?string {
        $accountNumber = config('services.moolre.account_number');
        $publicKey     = config('services.moolre.public_key');
        $baseUrl       = config('services.moolre.base_url');
        $email         = $tenant->contact_email ?: $tenant->email;

        if (! $accountNumber || ! $publicKey) {
            Log::error('PaymentGatewayService: Moolre credentials not configured');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Api-Pubkey' => $publicKey,
                'Accept'       => 'application/json',
            ])->timeout(15)->post($baseUrl, [
                'state'         => 'starter',
                'accountnumber' => $accountNumber,
                'reference'     => $payment->reference,
                'email'         => $email,
                'amount'        => (string) $payment->amount,
                'currency'      => config('billing.currency', 'GHS'),
                'callback'      => $callbackUrl . '?reference=' . $payment->reference,
                'tx_source'     => $txSource,
                'nonce_value'   => Str::random(16),
            ]);

            if ($response->successful() && $response->json('status') == 1) {
                $url = $response->json('data.authorization_url')
                    ?? $response->json('authorization_url');

                if ($url) {
                    return $url;
                }
            }

            Log::error('PaymentGatewayService: Moolre init failed', [
                'reference' => $payment->reference,
                'response'  => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('PaymentGatewayService: Moolre HTTP error', ['error' => $e->getMessage()]);
        }

        return null;
    }

    // ── Verify ────────────────────────────────────────────────────────────────

    /**
     * Verify a Paystack transaction server-side.
     * Returns the full API response array, or null on failure.
     *
     * @return array<string, mixed>|null
     */
    public function verifyPaystack(string $reference): ?array
    {
        $secretKey = config('services.paystack.secret_key');
        $baseUrl   = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');

        try {
            $response = Http::withToken($secretKey)
                ->timeout(15)
                ->get("{$baseUrl}/transaction/verify/{$reference}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('PaymentGatewayService: Paystack verify non-200', [
                'reference' => $reference,
                'status'    => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::error('PaymentGatewayService: Paystack verify error', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Verify a Moolre transaction server-side.
     * Returns the data payload, or null on failure.
     *
     * @return array<string, mixed>|null
     */
    public function verifyMoolre(string $reference): ?array
    {
        $accountNumber = config('services.moolre.account_number');
        $publicKey     = config('services.moolre.public_key');
        $baseUrl       = config('services.moolre.base_url');

        try {
            $response = Http::withHeaders([
                'X-Api-Pubkey' => $publicKey,
                'Accept'       => 'application/json',
            ])->timeout(15)->post($baseUrl, [
                'state'         => 'confirm',
                'accountnumber' => $accountNumber,
                'reference'     => $reference,
            ]);

            if ($response->successful() && $response->json('status') == 1) {
                return $response->json('data') ?? $response->json();
            }
        } catch (\Throwable $e) {
            Log::error('PaymentGatewayService: Moolre verify error', ['error' => $e->getMessage()]);
        }

        return null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Checkout URL for a payment, dispatching to the correct gateway.
     *
     * @param  string|null  $txSource  Optional Moolre tx_source override.
     */
    public function checkoutUrl(
        Payment $payment,
        Tenant  $tenant,
        string  $callbackUrl,
        array   $paystackMeta = [],
        string  $moolreTxSource = 'schoolms',
    ): ?string {
        return match ($payment->gateway) {
            Payment::GATEWAY_MOOLRE => $this->initMoolre($payment, $tenant, $callbackUrl, $moolreTxSource),
            default                 => $this->initPaystack($payment, $tenant, $callbackUrl, $paystackMeta),
        };
    }

    public function defaultGateway(): string
    {
        return config('billing.default_gateway', Payment::GATEWAY_PAYSTACK);
    }
}
