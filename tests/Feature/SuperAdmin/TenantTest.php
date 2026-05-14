<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantTest extends SuperAdminTestCase
{
    private AcademicYear $year;
    private AcademicTerm $term;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // TenantController::store() calls subscriptionService->createTrialSubscription()
        // which needs a current term to attach the subscription to.
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
            'slug'   => 'existing-school',
            'name'   => 'Existing School',
            'email'  => 'admin@existing.edu.gh',
            'status' => 'active',
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get('/tenants');

        $response->assertOk();
        $response->assertViewIs('superadmin.tenants.index');
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $response = $this->asGuest()->get('/tenants');

        $response->assertRedirect();
    }

    public function test_index_shows_existing_tenant(): void
    {
        $response = $this->asSuperAdmin()->get('/tenants');

        $response->assertSee('Existing School');
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function test_create_form_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get('/tenants/create');

        $response->assertOk();
        $response->assertViewIs('superadmin.tenants.create');
    }

    public function test_store_creates_tenant_and_trial_subscription(): void
    {
        $response = $this->asSuperAdmin()->post('/tenants', [
            'name'  => 'New Academy',
            'email' => 'admin@newacademy.edu.gh',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tenants', [
            'name'  => 'New Academy',
            'email' => 'admin@newacademy.edu.gh',
        ]);

        // Trial subscription should be auto-created
        $newTenant = Tenant::where('email', 'admin@newacademy.edu.gh')->first();
        $this->assertNotNull($newTenant);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $newTenant->id,
        ]);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->asSuperAdmin()->post('/tenants', [
            'email' => 'admin@noname.edu.gh',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_requires_email(): void
    {
        $response = $this->asSuperAdmin()->post('/tenants', [
            'name' => 'No Email School',
        ]);

        $response->assertSessionHasErrors('email');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get("/tenants/{$this->tenant->id}");

        $response->assertOk();
        $response->assertViewIs('superadmin.tenants.show');
    }

    public function test_show_displays_tenant_name(): void
    {
        $response = $this->asSuperAdmin()->get("/tenants/{$this->tenant->id}");

        $response->assertSee('Existing School');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function test_edit_form_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get("/tenants/{$this->tenant->id}/edit");

        $response->assertOk();
        $response->assertViewIs('superadmin.tenants.edit');
    }

    public function test_update_changes_tenant_status(): void
    {
        $response = $this->asSuperAdmin()->put("/tenants/{$this->tenant->id}", [
            'name'   => $this->tenant->name,
            'status' => 'suspended',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('tenants', [
            'id'     => $this->tenant->id,
            'status' => 'suspended',
        ]);
    }

    public function test_update_requires_name(): void
    {
        $response = $this->asSuperAdmin()->put("/tenants/{$this->tenant->id}", [
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('name');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_removes_tenant(): void
    {
        $tenantId = $this->tenant->id;

        $response = $this->asSuperAdmin()->delete("/tenants/{$tenantId}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('tenants', ['id' => $tenantId]);
    }
}
