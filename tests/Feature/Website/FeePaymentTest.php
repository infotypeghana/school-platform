<?php

namespace Tests\Feature\Website;

use App\Models\Fee;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\Http;

/**
 * Online fee payment flow — initiate, Paystack callback, Moolre callback.
 *
 * All gateway HTTP calls are faked via Http::fake() to avoid real API calls.
 * The parent portal session keys (multi-ward) are seeded via withSession().
 */
class FeePaymentTest extends WebsiteTestCase
{
    private Student $student;
    private Fee $fee;

    protected function setUp(): void
    {
        parent::setUp();

        $class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'first_name'      => 'Ama',
            'last_name'       => 'Boateng',
            'guardian_phone'  => '+233244000001',
            'status'          => 'active',
        ]);

        $this->fee = Fee::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $this->student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'tuition',
            'amount'      => 300.00,
            'amount_paid' => 0.00,
            'balance'     => 300.00,
            'status'      => 'unpaid',
            'due_date'    => now()->addDays(30)->toDateString(),
        ]);
    }

    // ── Initiate ──────────────────────────────────────────────────────────────

    public function test_initiate_requires_parent_session(): void
    {
        // No session → 403
        $this->post("/portal/fees/{$this->fee->id}/pay")
            ->assertStatus(403);
    }

    public function test_initiate_blocks_wrong_student_in_session(): void
    {
        // Session for a different student ID
        $this->withSession(['parent_portal_student_id' => 99999])
            ->post("/portal/fees/{$this->fee->id}/pay")
            ->assertStatus(403);
    }

    public function test_initiate_redirects_for_already_paid_fee(): void
    {
        $this->fee->update(['status' => 'paid', 'amount_paid' => 300, 'balance' => 0]);

        $this->withSession(['parent_portal_student_id' => $this->student->id])
            ->post("/portal/fees/{$this->fee->id}/pay")
            ->assertRedirect();
    }

    public function test_initiate_creates_pending_payment_and_redirects_to_gateway(): void
    {
        Http::fake([
            'https://api.paystack.co/*' => Http::response([
                'status' => true,
                'data'   => ['authorization_url' => 'https://checkout.paystack.com/fake123'],
            ], 200),
        ]);

        $response = $this->withSession(['parent_portal_student_id' => $this->student->id])
            ->post("/portal/fees/{$this->fee->id}/pay");

        $response->assertRedirect('https://checkout.paystack.com/fake123');

        $this->assertDatabaseHas('payments', [
            'fee_id'       => $this->fee->id,
            'payment_type' => Payment::TYPE_FEE,
            'status'       => Payment::STATUS_PENDING,
        ]);
    }

    public function test_initiate_reuses_existing_pending_payment(): void
    {
        Http::fake([
            'https://api.paystack.co/*' => Http::response([
                'status' => true,
                'data'   => ['authorization_url' => 'https://checkout.paystack.com/existing'],
            ], 200),
        ]);

        // Pre-create a pending payment
        Payment::create([
            'tenant_id'    => $this->tenant->id,
            'fee_id'       => $this->fee->id,
            'payment_type' => Payment::TYPE_FEE,
            'amount'       => 300.00,
            'currency'     => 'GHS',
            'gateway'      => Payment::GATEWAY_PAYSTACK,
            'reference'    => 'FEE-EXISTING',
            'status'       => Payment::STATUS_PENDING,
        ]);

        $this->withSession(['parent_portal_student_id' => $this->student->id])
            ->post("/portal/fees/{$this->fee->id}/pay");

        // Still only one payment record
        $this->assertEquals(1, Payment::where('fee_id', $this->fee->id)->count());
    }

    // ── Paystack callback ─────────────────────────────────────────────────────

    public function test_paystack_callback_confirms_successful_payment(): void
    {
        $payment = $this->createPendingPayment('FEE-TEST-PAYSTACK');

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data'   => ['status' => 'success', 'amount' => 30000, 'currency' => 'GHS'],
            ], 200),
        ]);

        $response = $this->get("/payment/fee/callback/paystack?reference=FEE-TEST-PAYSTACK");

        $response->assertOk();
        $this->assertDatabaseHas('payments', [
            'id'     => $payment->id,
            'status' => Payment::STATUS_SUCCESS,
        ]);
    }

    public function test_paystack_callback_updates_fee_amount_paid(): void
    {
        $payment = $this->createPendingPayment('FEE-PAID-PAYSTACK');

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data'   => ['status' => 'success', 'amount' => 30000],
            ], 200),
        ]);

        $this->get("/payment/fee/callback/paystack?reference=FEE-PAID-PAYSTACK");

        $this->assertDatabaseHas('fees', [
            'id'          => $this->fee->id,
            'amount_paid' => 300.00,
        ]);
    }

    public function test_paystack_callback_returns_pending_on_failed_verify(): void
    {
        $this->createPendingPayment('FEE-FAIL-PAYSTACK');

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => false,
            ], 400),
        ]);

        $response = $this->get("/payment/fee/callback/paystack?reference=FEE-FAIL-PAYSTACK");

        $response->assertOk();
        $response->assertViewHas('status', 'pending');
    }

    public function test_paystack_callback_with_unknown_reference_shows_error(): void
    {
        $response = $this->get('/payment/fee/callback/paystack?reference=NONEXISTENT');

        $response->assertOk();
        $response->assertViewHas('status', 'error');
    }

    public function test_paystack_callback_idempotent_for_already_confirmed_payment(): void
    {
        $payment = $this->createPendingPayment('FEE-IDEM-PAYSTACK');
        $payment->update(['status' => Payment::STATUS_SUCCESS, 'paid_at' => now()]);

        // No HTTP call should be made — already confirmed
        Http::fake();

        $response = $this->get("/payment/fee/callback/paystack?reference=FEE-IDEM-PAYSTACK");

        $response->assertOk();
        $response->assertViewHas('status', 'success');
        Http::assertNothingSent();
    }

    // ── Moolre callback ───────────────────────────────────────────────────────

    public function test_moolre_callback_confirms_successful_payment(): void
    {
        $payment = $this->createPendingPayment('FEE-TEST-MOOLRE', Payment::GATEWAY_MOOLRE);

        Http::fake([
            config('services.moolre.base_url', 'https://api.moolre.com/*') => Http::response([
                'status' => 1,
                'data'   => ['amount' => '300.00', 'currency' => 'GHS'],
            ], 200),
        ]);

        $response = $this->get("/payment/fee/callback/moolre?reference=FEE-TEST-MOOLRE");

        $response->assertOk();
        $this->assertDatabaseHas('payments', [
            'id'     => $payment->id,
            'status' => Payment::STATUS_SUCCESS,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createPendingPayment(string $reference, string $gateway = Payment::GATEWAY_PAYSTACK): Payment
    {
        return Payment::create([
            'tenant_id'    => $this->tenant->id,
            'fee_id'       => $this->fee->id,
            'payment_type' => Payment::TYPE_FEE,
            'amount'       => 300.00,
            'currency'     => 'GHS',
            'gateway'      => $gateway,
            'reference'    => $reference,
            'status'       => Payment::STATUS_PENDING,
            'metadata'     => ['student_id' => $this->student->id],
        ]);
    }
}
