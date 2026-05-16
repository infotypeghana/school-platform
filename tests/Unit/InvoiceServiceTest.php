<?php

namespace Tests\Unit;

use App\Jobs\SendSmsJob;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * InvoiceService — unit tests covering:
 *  - generateForSubscription(): amount calculation, numbering, SMS dispatch
 *  - generateTermInvoices(): batch creation, skip-existing guard
 *  - Invoice::nextNumber(): sequential numbering
 */
class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;
    private Tenant $tenant;
    private AcademicYear $year;
    private AcademicTerm $term;
    private SubscriptionPackage $package;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->service = new InvoiceService();

        $this->tenant = Tenant::create([
            'uuid'          => Str::uuid(),
            'slug'          => 'invoice-school',
            'name'          => 'Invoice School',
            'email'         => 'admin@invoice.edu.gh',
            'contact_phone' => '+233244999000',
            'status'        => 'active',
        ]);

        $this->year = AcademicYear::create(['year_label' => 'Invoice-Unit-Test-Year', 'is_current' => true]);

        $this->term = AcademicTerm::create([
            'academic_year_id' => $this->year->id,
            'term_number'      => 1,
            'term_name'        => 'First Term',
            'start_date'       => now()->subDays(30)->toDateString(),
            'end_date'         => now()->addDays(60)->toDateString(),
            'is_current'       => true,
        ]);

        $this->package = SubscriptionPackage::create([
            'name'              => 'Standard',
            'slug'              => 'standard-unit',
            'price_per_student' => 10.00,
            'billing_cycle'     => 'term',
            'is_active'         => true,
            'description'       => 'Standard plan',
        ]);

        $this->subscription = Subscription::create([
            'tenant_id'         => $this->tenant->id,
            'academic_year_id'  => $this->year->id,
            'term_id'           => $this->term->id,
            'package_id'        => $this->package->id,
            'student_count'     => 50,
            'price_per_student' => 10.00,
            'amount'            => 500.00,
            'start_date'        => now()->subDays(30)->toDateString(),
            'end_date'          => now()->addDays(60)->toDateString(),
            'status'            => Subscription::STATUS_ACTIVE,
            'activated_at'      => now()->subDays(30),
        ]);
    }

    // ── generateForSubscription ───────────────────────────────────────────────

    public function test_generates_invoice_for_subscription(): void
    {
        $invoice = $this->service->generateForSubscription(
            $this->subscription,
            $this->package,
            $this->term,
        );

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_invoice_amount_uses_active_student_count(): void
    {
        // Create 5 active students for this tenant
        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);
        Student::factory()->count(5)->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'status'          => 'active',
        ]);
        // 1 withdrawn — should not count toward active billing
        Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'status'          => 'withdrawn',
        ]);

        $invoice = $this->service->generateForSubscription(
            $this->subscription,
            $this->package,
            $this->term,
        );

        $this->assertEquals(5, $invoice->student_count);
        $this->assertEquals(50.00, $invoice->amount); // 5 × 10.00
    }

    public function test_invoice_uses_package_minimum_students_when_actual_count_is_lower(): void
    {
        // Package with min_students = 20; only 3 active students
        $package = SubscriptionPackage::create([
            'name'              => 'Min Plan',
            'slug'              => 'min-plan-unit',
            'price_per_student' => 8.00,
            'billing_cycle'     => 'term',
            'is_active'         => true,
            'description'       => 'Min plan',
            'min_students'      => 20,
        ]);

        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);
        Student::factory()->count(3)->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'status'          => 'active',
        ]);

        $invoice = $this->service->generateForSubscription(
            $this->subscription,
            $package,
            $this->term,
        );

        // Billed at minimum 20 × 8.00 = 160.00
        $this->assertEquals(160.00, $invoice->amount);
    }

    public function test_invoice_number_format_is_correct(): void
    {
        $invoice = $this->service->generateForSubscription(
            $this->subscription,
            $this->package,
            $this->term,
        );

        $year = now()->year;
        $this->assertMatchesRegularExpression("/^INV-{$year}-\d{4}$/", $invoice->invoice_number);
    }

    public function test_invoice_due_date_is_14_days_by_default(): void
    {
        $invoice = $this->service->generateForSubscription(
            $this->subscription,
            $this->package,
            $this->term,
        );

        $this->assertEquals(
            now()->addDays(14)->toDateString(),
            $invoice->due_date->toDateString(),
        );
    }

    public function test_custom_due_days_applied(): void
    {
        $invoice = $this->service->generateForSubscription(
            $this->subscription,
            $this->package,
            $this->term,
            dueDays: 30,
        );

        $this->assertEquals(
            now()->addDays(30)->toDateString(),
            $invoice->due_date->toDateString(),
        );
    }

    public function test_sms_dispatched_after_invoice_creation(): void
    {
        $this->service->generateForSubscription(
            $this->subscription,
            $this->package,
            $this->term,
        );

        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_no_sms_dispatched_when_tenant_has_no_phone(): void
    {
        $this->tenant->update(['contact_phone' => null, 'phone' => null]);

        $this->service->generateForSubscription(
            $this->subscription,
            $this->package,
            $this->term,
        );

        Queue::assertNotPushed(SendSmsJob::class);
    }

    // ── Invoice::nextNumber() ─────────────────────────────────────────────────

    public function test_next_number_starts_at_0001(): void
    {
        $number = Invoice::nextNumber();
        $year   = now()->year;

        $this->assertEquals("INV-{$year}-0001", $number);
    }

    public function test_next_number_increments_sequentially(): void
    {
        $year = now()->year;

        // Simulate existing invoice
        Invoice::create([
            'tenant_id'         => $this->tenant->id,
            'invoice_number'    => "INV-{$year}-0005",
            'package_name'      => 'Plan',
            'billing_cycle'     => 'term',
            'student_count'     => 10,
            'price_per_student' => 10.00,
            'amount'            => 100.00,
            'status'            => 'draft',
            'due_date'          => now()->addDays(14)->toDateString(),
        ]);

        $this->assertEquals("INV-{$year}-0006", Invoice::nextNumber());
    }

    // ── generateTermInvoices ──────────────────────────────────────────────────

    public function test_generate_term_invoices_creates_for_active_subscriptions(): void
    {
        $count = $this->service->generateTermInvoices($this->term);

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $this->tenant->id,
            'term_id'   => $this->term->id,
        ]);
    }

    public function test_generate_term_invoices_skips_existing_term_invoice(): void
    {
        // Already generated for this term
        $this->service->generateTermInvoices($this->term);

        // Should skip on second call
        $count = $this->service->generateTermInvoices($this->term);

        $this->assertEquals(0, $count);
        $this->assertEquals(1, Invoice::where('term_id', $this->term->id)->count());
    }

    public function test_generate_term_invoices_skips_subscriptions_without_package(): void
    {
        $this->subscription->update(['package_id' => null]);

        $count = $this->service->generateTermInvoices($this->term);

        $this->assertEquals(0, $count);
    }

    public function test_generate_term_invoices_skips_suspended_subscriptions(): void
    {
        $this->subscription->update(['status' => Subscription::STATUS_SUSPENDED]);

        $count = $this->service->generateTermInvoices($this->term);

        $this->assertEquals(0, $count);
    }

    public function test_generate_term_invoices_processes_trial_subscriptions(): void
    {
        $this->subscription->update(['status' => Subscription::STATUS_TRIAL]);

        $count = $this->service->generateTermInvoices($this->term);

        $this->assertEquals(1, $count);
    }
}
