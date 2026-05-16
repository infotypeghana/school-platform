<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for PaymentController (subscription payment flow).
 *
 * These routes live on the root domain — no subdomain required.
 * We mock PaymentGatewayService to avoid real HTTP calls to Paystack/Moolre.
 */
class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant       $tenant;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $year = AcademicYear::create(['year_label' => '2024/2025', 'is_current' => true]);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'term_number'      => 1,
            'term_name'        => 'First Term',
            'start_date'       => now()->subDays(30)->toDateString(),
            'end_date'         => now()->addDays(60)->toDateString(),
            'is_current'       => true,
        ]);

        $this->tenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'pay-test-school',
            'name'   => 'Pay Test School',
            'email'  => 'admin@paytest.edu.gh',
            'status' => 'trial',
        ]);

        $this->subscription = Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $year->id,
            'term_id'          => $term->id,
            'amount'           => 800,
            'start_date'       => now()->subDays(30)->toDateString(),
            'end_date'         => now()->addDays(60)->toDateString(),
            'status'           => Subscription::STATUS_TRIAL,
            'is_trial'         => true,
            'activated_at'     => now()->subDays(30),
        ]);
    }

    // ── Payment page ──────────────────────────────────────────────────────────

    public function test_payment_page_is_accessible(): void
    {
        $this->get('/pay/' . $this->tenant->slug . '/page')->assertOk();
    }

    // ── Initiate ──────────────────────────────────────────────────────────────

    public function test_initiate_redirects_to_gateway_checkout(): void
    {
        $this->mockGateway('https://paystack.co/pay/test-url');

        $this->get('/pay/' . $this->tenant->slug)->assertRedirect('https://paystack.co/pay/test-url');
    }

    public function test_initiate_creates_payment_record_with_subscription_type(): void
    {
        $this->mockGateway('https://gateway.test/checkout');

        $this->get('/pay/' . $this->tenant->slug);

        $this->assertDatabaseHas('payments', [
            'tenant_id'       => $this->tenant->id,
            'subscription_id' => $this->subscription->id,
            'payment_type'    => Payment::TYPE_SUBSCRIPTION,
            'status'          => Payment::STATUS_PENDING,
        ]);
    }

    public function test_initiate_deduplicates_pending_payments(): void
    {
        $this->mockGateway('https://gateway.test/checkout');

        $this->get('/pay/' . $this->tenant->slug);
        $this->get('/pay/' . $this->tenant->slug);

        $this->assertCount(1, Payment::where('tenant_id', $this->tenant->id)->get());
    }

    public function test_initiate_returns_404_when_no_subscription(): void
    {
        $bare = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'no-sub-school',
            'name'   => 'No Sub School',
            'email'  => 'admin@nosub.edu.gh',
            'status' => 'pending',
        ]);

        $this->get('/pay/' . $bare->slug)->assertNotFound();
    }

    public function test_initiate_shows_error_when_gateway_returns_null(): void
    {
        $this->mockGateway(null);

        $this->get('/pay/' . $this->tenant->slug)
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── Paystack callback ─────────────────────────────────────────────────────

    public function test_paystack_callback_shows_success_for_already_confirmed_payment(): void
    {
        $payment = Payment::create([
            'tenant_id'       => $this->tenant->id,
            'subscription_id' => $this->subscription->id,
            'payment_type'    => Payment::TYPE_SUBSCRIPTION,
            'amount'          => 800,
            'currency'        => 'GHS',
            'gateway'         => 'paystack',
            'reference'       => 'SMS-ALREADYDONE',
            'status'          => Payment::STATUS_SUCCESS,
            'paid_at'         => now(),
        ]);

        $this->get('/payment/callback/paystack?reference=' . $payment->reference)
            ->assertOk()
            ->assertViewHas('status', 'success');
    }

    public function test_paystack_callback_shows_error_without_reference(): void
    {
        $this->get('/payment/callback/paystack')
            ->assertOk()
            ->assertViewHas('status', 'error');
    }

    public function test_paystack_callback_shows_error_for_unknown_reference(): void
    {
        $this->get('/payment/callback/paystack?reference=UNKNOWN-REF-123')
            ->assertOk()
            ->assertViewHas('status', 'error');
    }

    public function test_moolre_callback_shows_error_without_reference(): void
    {
        $this->get('/payment/callback/moolre')
            ->assertOk()
            ->assertViewHas('status', 'error');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function mockGateway(?string $checkoutUrl): void
    {
        $mock = $this->createMock(PaymentGatewayService::class);

        $mock->method('defaultGateway')->willReturn('paystack');

        $mock->method('checkoutUrl')->willReturn($checkoutUrl);

        $this->app->instance(PaymentGatewayService::class, $mock);
    }
}
