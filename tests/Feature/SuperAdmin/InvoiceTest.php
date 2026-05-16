<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Super-admin invoice management — index, show, generate, mark-paid, void.
 *
 * Routes are on the superadmin.{APP_DOMAIN} domain (handled by SuperAdminTestCase).
 */
class InvoiceTest extends SuperAdminTestCase
{
    private Tenant $tenant;
    private AcademicYear $year;
    private AcademicTerm $term;
    private Subscription $subscription;
    private SubscriptionPackage $package;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = AcademicYear::create(['year_label' => '2024/2025', 'is_current' => true]);

        $this->term = AcademicTerm::create([
            'academic_year_id' => $this->year->id,
            'term_number'      => 1,
            'term_name'        => 'First Term',
            'start_date'       => now()->subDays(30)->toDateString(),
            'end_date'         => now()->addDays(60)->toDateString(),
            'is_current'       => true,
        ]);

        $this->tenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'billing-school',
            'name'   => 'Billing Academy',
            'email'  => 'admin@billing.edu.gh',
            'status' => 'active',
        ]);

        $this->package = SubscriptionPackage::create([
            'name'              => 'Standard',
            'slug'              => 'standard',
            'price_per_student' => 12.00,
            'billing_cycle'     => 'term',
            'is_active'         => true,
            'description'       => 'Standard plan',
        ]);

        $this->subscription = Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $this->year->id,
            'term_id'          => $this->term->id,
            'package_id'       => $this->package->id,
            'student_count'    => 80,
            'price_per_student'=> 12.00,
            'amount'           => 960.00,
            'start_date'       => now()->subDays(30)->toDateString(),
            'end_date'         => now()->addDays(60)->toDateString(),
            'status'           => Subscription::STATUS_ACTIVE,
            'activated_at'     => now()->subDays(30),
        ]);

        $this->invoice = Invoice::create([
            'tenant_id'         => $this->tenant->id,
            'subscription_id'   => $this->subscription->id,
            'term_id'           => $this->term->id,
            'invoice_number'    => 'INV-2026-0001',
            'package_name'      => 'Standard',
            'billing_cycle'     => 'term',
            'student_count'     => 80,
            'price_per_student' => 12.00,
            'amount'            => 960.00,
            'status'            => 'sent',
            'due_date'          => now()->addDays(14)->toDateString(),
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_invoice_index_accessible_to_super_admin(): void
    {
        $this->asSuperAdmin()->get('/invoices')->assertOk();
    }

    public function test_invoice_index_shows_invoice_number(): void
    {
        $this->asSuperAdmin()->get('/invoices')->assertSee('INV-2026-0001');
    }

    public function test_invoice_index_shows_tenant_name(): void
    {
        $this->asSuperAdmin()->get('/invoices')->assertSee('Billing Academy');
    }

    public function test_guest_cannot_access_invoice_index(): void
    {
        $this->asGuest()->get('/invoices')->assertRedirect();
    }

    public function test_invoice_index_filters_by_status(): void
    {
        // Create a paid invoice
        Invoice::create([
            'tenant_id'         => $this->tenant->id,
            'invoice_number'    => 'INV-2026-0002',
            'package_name'      => 'Standard',
            'billing_cycle'     => 'term',
            'student_count'     => 80,
            'price_per_student' => 12.00,
            'amount'            => 960.00,
            'status'            => 'paid',
            'due_date'          => now()->subDays(7)->toDateString(),
            'paid_at'           => now()->subDays(5),
        ]);

        // Filter by 'sent' — should show INV-0001, not INV-0002
        $response = $this->asSuperAdmin()->get('/invoices?status=sent');
        $response->assertSee('INV-2026-0001');
        $response->assertDontSee('INV-2026-0002');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_invoice_show_returns_200(): void
    {
        $this->asSuperAdmin()->get("/invoices/{$this->invoice->id}")->assertOk();
    }

    public function test_invoice_show_displays_details(): void
    {
        $this->asSuperAdmin()
            ->get("/invoices/{$this->invoice->id}")
            ->assertSee('INV-2026-0001')
            ->assertSee('Billing Academy');
    }

    // ── Generate ──────────────────────────────────────────────────────────────

    public function test_generate_creates_invoice_and_redirects(): void
    {
        // Remove existing invoice so a new one can be generated
        $this->invoice->delete();

        $response = $this->asSuperAdmin()->post('/invoices/generate', [
            'subscription_id' => $this->subscription->id,
            'package_id'      => $this->package->id,
            'term_id'         => $this->term->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'tenant_id'    => $this->tenant->id,
            'package_name' => 'Standard',
        ]);
    }

    public function test_generate_requires_subscription_id(): void
    {
        $this->asSuperAdmin()->post('/invoices/generate', [
            'package_id' => $this->package->id,
            'term_id'    => $this->term->id,
        ])->assertSessionHasErrors('subscription_id');
    }

    // ── Mark paid ─────────────────────────────────────────────────────────────

    public function test_mark_paid_updates_status(): void
    {
        $this->asSuperAdmin()->post("/invoices/{$this->invoice->id}/mark-paid");

        $this->assertDatabaseHas('invoices', [
            'id'     => $this->invoice->id,
            'status' => 'paid',
        ]);
    }

    public function test_mark_paid_on_already_paid_invoice_returns_error(): void
    {
        $this->invoice->update(['status' => 'paid', 'paid_at' => now()]);

        $this->asSuperAdmin()
            ->post("/invoices/{$this->invoice->id}/mark-paid")
            ->assertSessionHas('error');
    }

    // ── Void ─────────────────────────────────────────────────────────────────

    public function test_void_invoice_updates_status(): void
    {
        $this->asSuperAdmin()->post("/invoices/{$this->invoice->id}/void");

        $this->assertDatabaseHas('invoices', [
            'id'     => $this->invoice->id,
            'status' => 'void',
        ]);
    }

    public function test_void_paid_invoice_returns_error(): void
    {
        $this->invoice->update(['status' => 'paid', 'paid_at' => now()]);

        $this->asSuperAdmin()
            ->post("/invoices/{$this->invoice->id}/void")
            ->assertSessionHas('error');
    }
}
