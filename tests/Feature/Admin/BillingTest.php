<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;

/**
 * Admin billing page — shows invoices for the current tenant.
 *
 * Route: GET /billing  →  admin.billing  (AdminTestCase domain)
 */
class BillingTest extends AdminTestCase
{
    // ── Access ────────────────────────────────────────────────────────────────

    public function test_billing_page_accessible_to_authenticated_admin(): void
    {
        $this->asAdmin()->get('/billing')->assertOk();
    }

    public function test_guest_is_redirected_from_billing(): void
    {
        $this->asGuest()->get('/billing')->assertRedirect();
    }

    // ── Content ───────────────────────────────────────────────────────────────

    public function test_billing_page_shows_invoice_number(): void
    {
        Invoice::create([
            'tenant_id'         => $this->tenant->id,
            'term_id'           => $this->term->id,
            'invoice_number'    => 'INV-2026-0001',
            'package_name'      => 'Basic Plan',
            'billing_cycle'     => 'term',
            'student_count'     => 50,
            'price_per_student' => 10.00,
            'amount'            => 500.00,
            'status'            => 'sent',
            'due_date'          => now()->addDays(14)->toDateString(),
        ]);

        $this->asAdmin()->get('/billing')->assertSee('INV-2026-0001');
    }

    public function test_billing_page_shows_outstanding_amount(): void
    {
        Invoice::create([
            'tenant_id'         => $this->tenant->id,
            'term_id'           => $this->term->id,
            'invoice_number'    => 'INV-2026-0002',
            'package_name'      => 'Basic Plan',
            'billing_cycle'     => 'term',
            'student_count'     => 50,
            'price_per_student' => 10.00,
            'amount'            => 500.00,
            'status'            => 'sent',
            'due_date'          => now()->addDays(14)->toDateString(),
        ]);

        $this->asAdmin()->get('/billing')->assertSee('500');
    }

    public function test_billing_page_does_not_count_paid_invoices_as_outstanding(): void
    {
        Invoice::create([
            'tenant_id'         => $this->tenant->id,
            'term_id'           => $this->term->id,
            'invoice_number'    => 'INV-2026-0003',
            'package_name'      => 'Basic Plan',
            'billing_cycle'     => 'term',
            'student_count'     => 50,
            'price_per_student' => 10.00,
            'amount'            => 500.00,
            'status'            => 'paid',
            'due_date'          => now()->subDays(7)->toDateString(),
            'paid_at'           => now()->subDays(5),
        ]);

        // Outstanding should be 0, not 500
        $response = $this->asAdmin()->get('/billing');
        $response->assertOk();
        $response->assertViewHas('outstanding', 0.0);
    }

    public function test_billing_page_only_shows_own_tenant_invoices(): void
    {
        // Invoice for a different tenant should not appear
        $otherTenant = \App\Models\Tenant::create([
            'uuid'   => \Illuminate\Support\Str::uuid(),
            'slug'   => 'other-school',
            'name'   => 'Other School',
            'email'  => 'other@school.edu.gh',
            'status' => 'active',
        ]);

        Invoice::create([
            'tenant_id'         => $otherTenant->id,
            'invoice_number'    => 'INV-2026-9999',
            'package_name'      => 'Other Plan',
            'billing_cycle'     => 'term',
            'student_count'     => 10,
            'price_per_student' => 5.00,
            'amount'            => 50.00,
            'status'            => 'sent',
            'due_date'          => now()->addDays(7)->toDateString(),
        ]);

        $this->asAdmin()->get('/billing')->assertDontSee('INV-2026-9999');
    }

    public function test_billing_page_shows_empty_state_with_no_invoices(): void
    {
        $response = $this->asAdmin()->get('/billing');
        $response->assertOk();
        $response->assertViewHas('outstanding', 0.0);
    }
}
