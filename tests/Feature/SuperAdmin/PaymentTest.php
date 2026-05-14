<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Str;

class PaymentTest extends SuperAdminTestCase
{
    private Tenant $school;
    private Payment $payment;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $year = AcademicYear::create([
            'year_label' => '2024/2025',
            'is_current' => true,
        ]);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'term_number'      => 1,
            'term_name'        => 'First Term',
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'is_current'       => true,
        ]);

        $this->school = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'pay-school',
            'name'   => 'Payment School',
            'email'  => 'admin@payschool.edu.gh',
            'status' => 'active',
        ]);

        $this->subscription = Subscription::create([
            'tenant_id'        => $this->school->id,
            'academic_year_id' => $year->id,
            'term_id'          => $term->id,
            'amount'           => 850,
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'status'           => Subscription::STATUS_ACTIVE,
            'activated_at'     => now()->subDays(60),
        ]);

        $this->payment = Payment::create([
            'tenant_id'       => $this->school->id,
            'subscription_id' => $this->subscription->id,
            'amount'          => 850,
            'currency'        => 'GHS',
            'gateway'         => Payment::GATEWAY_PAYSTACK,
            'reference'       => 'PS-REF-' . Str::random(8),
            'status'          => Payment::STATUS_SUCCESS,
            'paid_at'         => now(),
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get('/payments');

        $response->assertOk();
        $response->assertViewIs('superadmin.payments.index');
    }

    public function test_index_lists_payments(): void
    {
        $response = $this->asSuperAdmin()->get('/payments');

        $response->assertSee('Payment School');
        $response->assertSee($this->payment->reference);
    }

    public function test_guest_redirected_from_payments(): void
    {
        $this->asGuest()->get('/payments')->assertRedirect();
    }

    public function test_index_passes_total_revenue_to_view(): void
    {
        $response = $this->asSuperAdmin()->get('/payments');

        $response->assertViewHas('totalRevenue');
        $this->assertEquals(850, $response->viewData('totalRevenue'));
    }

    // ── Filters ───────────────────────────────────────────────────────────────

    public function test_index_filters_by_status(): void
    {
        // The setUp payment is 'success'; create a pending one
        Payment::create([
            'tenant_id'       => $this->school->id,
            'subscription_id' => $this->subscription->id,
            'amount'          => 500,
            'currency'        => 'GHS',
            'gateway'         => Payment::GATEWAY_PAYSTACK,
            'reference'       => 'PS-PEND-' . Str::random(8),
            'status'          => Payment::STATUS_PENDING,
        ]);

        $response = $this->asSuperAdmin()->get('/payments?status=pending');

        // Should see pending but not the success reference
        $response->assertDontSee($this->payment->reference);
    }

    public function test_index_filters_by_gateway(): void
    {
        $moolreRef = 'ML-REF-' . Str::random(8);
        Payment::create([
            'tenant_id'       => $this->school->id,
            'subscription_id' => $this->subscription->id,
            'amount'          => 600,
            'currency'        => 'GHS',
            'gateway'         => Payment::GATEWAY_MOOLRE,
            'reference'       => $moolreRef,
            'status'          => Payment::STATUS_SUCCESS,
            'paid_at'         => now(),
        ]);

        $response = $this->asSuperAdmin()->get('/payments?gateway=moolre');

        $response->assertSee($moolreRef);
        $response->assertDontSee($this->payment->reference);
    }

    public function test_index_searches_by_reference(): void
    {
        $uniqueRef = 'UNIQUE-REF-XYZ-123';
        Payment::create([
            'tenant_id'       => $this->school->id,
            'subscription_id' => $this->subscription->id,
            'amount'          => 200,
            'currency'        => 'GHS',
            'gateway'         => Payment::GATEWAY_PAYSTACK,
            'reference'       => $uniqueRef,
            'status'          => Payment::STATUS_PENDING,
        ]);

        $response = $this->asSuperAdmin()->get('/payments?search=UNIQUE-REF');

        $response->assertSee($uniqueRef);
        $response->assertDontSee($this->payment->reference);
    }

    public function test_index_searches_by_school_name(): void
    {
        $response = $this->asSuperAdmin()->get('/payments?search=Payment+School');

        $response->assertSee('Payment School');
        $response->assertSee($this->payment->reference);
    }
}
