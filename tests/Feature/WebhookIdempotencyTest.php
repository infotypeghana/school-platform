<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Subscription $subscription;
    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // Wire up minimal fake credentials for HMAC tests
        Config::set('services.paystack.secret_key', 'test_secret_key_for_hmac_tests');
        Config::set('services.moolre.public_key', 'moolre_test_pubkey');
        Config::set('services.moolre.account_number', 'test_account');

        // Minimal data setup
        $year = AcademicYear::create(['year_label' => '2024/2025', 'is_current' => true]);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'term_number'      => 1,
            'term_name'        => 'First Term',
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'is_current'       => true,
        ]);

        $this->tenant = Tenant::create([
            'uuid'   => \Illuminate\Support\Str::uuid(),
            'slug'   => 'accra-academy',
            'name'   => 'Accra Academy',
            'email'  => 'admin@accraacademy.edu.gh',
            'status' => 'grace',
        ]);

        $this->subscription = Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $year->id,
            'term_id'          => $term->id,
            'amount'           => 500,
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->subDay()->toDateString(),
            'grace_ends_at'    => now()->addDays(3),
            'status'           => Subscription::STATUS_GRACE,
        ]);

        $this->payment = Payment::create([
            'tenant_id'       => $this->tenant->id,
            'subscription_id' => $this->subscription->id,
            'amount'          => 500,
            'currency'        => 'GHS',
            'gateway'         => Payment::GATEWAY_PAYSTACK,
            'reference'       => 'SMS-TESTREF001',
            'status'          => Payment::STATUS_PENDING,
        ]);
    }

    // ── Paystack webhook signature verification ───────────────────────────────

    public function test_paystack_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/webhooks/paystack', [], [
            'X-Paystack-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(401);
    }

    public function test_paystack_webhook_accepts_valid_signature(): void
    {
        $payload   = $this->paystackPayload('SMS-TESTREF001');
        $signature = $this->paystackSignature($payload);

        $response = $this->postJson(
            '/webhooks/paystack',
            json_decode($payload, true),
            ['X-Paystack-Signature' => $signature]
        );

        $response->assertStatus(200);
    }

    // ── Paystack payment processing ───────────────────────────────────────────

    public function test_paystack_webhook_activates_subscription(): void
    {
        $payload   = $this->paystackPayload('SMS-TESTREF001');
        $signature = $this->paystackSignature($payload);

        $this->postJson(
            '/webhooks/paystack',
            json_decode($payload, true),
            ['X-Paystack-Signature' => $signature]
        );

        $this->payment->refresh();
        $this->subscription->refresh();

        $this->assertSame(Payment::STATUS_SUCCESS, $this->payment->status);
        $this->assertSame(Subscription::STATUS_ACTIVE, $this->subscription->status);
        $this->assertNotNull($this->payment->paid_at);
    }

    // ── Idempotency: duplicate webhook is a no-op ──────────────────────────────

    public function test_paystack_webhook_is_idempotent(): void
    {
        $payload   = $this->paystackPayload('SMS-TESTREF001');
        $signature = $this->paystackSignature($payload);

        // First webhook — activates
        $this->postJson('/webhooks/paystack', json_decode($payload, true), ['X-Paystack-Signature' => $signature])
            ->assertStatus(200);

        $this->payment->refresh();
        $firstActivatedAt = $this->subscription->fresh()->activated_at;

        // Second webhook (duplicate) — must not re-process
        $this->postJson('/webhooks/paystack', json_decode($payload, true), ['X-Paystack-Signature' => $signature])
            ->assertStatus(200);

        $this->payment->refresh();
        $this->assertSame(Payment::STATUS_SUCCESS, $this->payment->status);

        // activated_at must not have changed
        $this->assertEquals(
            $firstActivatedAt?->toDateTimeString(),
            $this->subscription->fresh()->activated_at?->toDateTimeString()
        );
    }

    // ── Unknown reference ─────────────────────────────────────────────────────

    public function test_paystack_webhook_ignores_unknown_reference(): void
    {
        $payload   = $this->paystackPayload('UNKNOWN-REF-XYZ');
        $signature = $this->paystackSignature($payload);

        $response = $this->postJson(
            '/webhooks/paystack',
            json_decode($payload, true),
            ['X-Paystack-Signature' => $signature]
        );

        $response->assertStatus(200); // 200 to stop Paystack retrying

        // Our payment should remain pending
        $this->payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $this->payment->status);
    }

    // ── Non-charge events are ignored ─────────────────────────────────────────

    public function test_paystack_webhook_ignores_non_charge_events(): void
    {
        $payload   = json_encode(['event' => 'transfer.success', 'data' => ['reference' => 'SMS-TESTREF001']]);
        $signature = $this->paystackSignature($payload);

        $response = $this->postJson(
            '/webhooks/paystack',
            json_decode($payload, true),
            ['X-Paystack-Signature' => $signature]
        );

        $response->assertStatus(200);

        $this->payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $this->payment->status);
    }

    // ── Moolre webhook ────────────────────────────────────────────────────────

    public function test_moolre_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/webhooks/moolre', [], [
            'X-Moolre-Signature' => 'wrong_signature',
        ]);

        $response->assertStatus(401);
    }

    public function test_moolre_webhook_activates_subscription(): void
    {
        $this->payment->update(['gateway' => Payment::GATEWAY_MOOLRE]);

        $payload    = $this->moolrePayload('SMS-TESTREF001', 500);
        $signature  = $this->moolreSignature(json_encode($payload));

        $response = $this->postJson('/webhooks/moolre', $payload, [
            'X-Moolre-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $this->payment->refresh();
        $this->subscription->refresh();

        $this->assertSame(Payment::STATUS_SUCCESS, $this->payment->status);
        $this->assertSame(Subscription::STATUS_ACTIVE, $this->subscription->status);
    }

    public function test_moolre_webhook_rejects_underpayment(): void
    {
        $this->payment->update(['gateway' => Payment::GATEWAY_MOOLRE]);

        $payload   = $this->moolrePayload('SMS-TESTREF001', 100); // underpayment (expected 500)
        $signature = $this->moolreSignature(json_encode($payload));

        $this->postJson('/webhooks/moolre', $payload, [
            'X-Moolre-Signature' => $signature,
        ])->assertStatus(200);

        $this->payment->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $this->payment->status);
        $this->subscription->refresh();
        $this->assertNotSame(Subscription::STATUS_ACTIVE, $this->subscription->status);
    }

    public function test_moolre_webhook_is_idempotent(): void
    {
        $this->payment->update(['gateway' => Payment::GATEWAY_MOOLRE]);

        $payload   = $this->moolrePayload('SMS-TESTREF001', 500);
        $signature = $this->moolreSignature(json_encode($payload));

        // First call — activates
        $this->postJson('/webhooks/moolre', $payload, ['X-Moolre-Signature' => $signature])
            ->assertStatus(200);

        $activatedAt = $this->subscription->fresh()->activated_at;

        // Second call — idempotent
        $this->postJson('/webhooks/moolre', $payload, ['X-Moolre-Signature' => $signature])
            ->assertStatus(200);

        $this->assertEquals(
            $activatedAt?->toDateTimeString(),
            $this->subscription->fresh()->activated_at?->toDateTimeString()
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function paystackPayload(string $reference): string
    {
        return json_encode([
            'event' => 'charge.success',
            'data'  => [
                'id'        => 987654,
                'reference' => $reference,
                'amount'    => 50000, // pesewas
                'status'    => 'success',
            ],
        ]);
    }

    private function paystackSignature(string $payload): string
    {
        return hash_hmac('sha512', $payload, config('services.paystack.secret_key'));
    }

    private function moolrePayload(string $reference, float $amount): array
    {
        return [
            'status' => 1,
            'data'   => [
                'reference' => $reference,
                'amount'    => $amount,
            ],
        ];
    }

    private function moolreSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, config('services.moolre.public_key'));
    }
}
