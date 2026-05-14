<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Str;

class SubscriptionManagementTest extends SuperAdminTestCase
{
    private Tenant $school;
    private Subscription $sub;

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
            'slug'   => 'demo-school',
            'name'   => 'Demo School',
            'email'  => 'admin@demo.edu.gh',
            'status' => 'active',
        ]);

        $this->sub = Subscription::create([
            'tenant_id'        => $this->school->id,
            'academic_year_id' => $year->id,
            'term_id'          => $term->id,
            'plan_id'          => 'basic',
            'amount'           => 500,
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'status'           => Subscription::STATUS_TRIAL,
            'is_trial'         => true,
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get('/subscriptions');

        $response->assertOk();
        $response->assertViewIs('superadmin.subscriptions.index');
    }

    public function test_index_lists_subscriptions(): void
    {
        $response = $this->asSuperAdmin()->get('/subscriptions');

        $response->assertSee('Demo School');
    }

    public function test_guest_redirected_from_index(): void
    {
        $this->asGuest()->get('/subscriptions')->assertRedirect();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get("/subscriptions/{$this->sub->id}");

        $response->assertOk();
        $response->assertViewIs('superadmin.subscriptions.show');
    }

    public function test_show_displays_subscription_details(): void
    {
        $response = $this->asSuperAdmin()->get("/subscriptions/{$this->sub->id}");

        $response->assertSee('Demo School');
        $response->assertSee('First Term');
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function test_edit_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get("/subscriptions/{$this->sub->id}/edit");

        $response->assertOk();
        $response->assertViewIs('superadmin.subscriptions.edit');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_changes_amount(): void
    {
        $response = $this->asSuperAdmin()->put("/subscriptions/{$this->sub->id}", [
            'status'   => 'active',
            'amount'   => 850.00,
            'end_date' => now()->addDays(30)->toDateString(),
            'is_trial' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->sub->id,
            'amount' => 850,
        ]);
    }

    public function test_update_changes_status(): void
    {
        $this->asSuperAdmin()->put("/subscriptions/{$this->sub->id}", [
            'status'   => 'active',
            'amount'   => 500,
            'end_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->sub->id,
            'status' => 'active',
        ]);
    }

    public function test_update_requires_valid_status(): void
    {
        $response = $this->asSuperAdmin()->put("/subscriptions/{$this->sub->id}", [
            'status'   => 'invalid-status',
            'amount'   => 500,
            'end_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_update_requires_amount(): void
    {
        $response = $this->asSuperAdmin()->put("/subscriptions/{$this->sub->id}", [
            'status'   => 'active',
            'end_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertSessionHasErrors('amount');
    }

    // ── Index filters ────────────────────────────────────────────────────────

    public function test_index_filters_by_status(): void
    {
        // sub created in setUp is 'trial'; create an 'active' one
        Subscription::create([
            'tenant_id'        => $this->school->id,
            'academic_year_id' => $this->sub->academic_year_id,
            'term_id'          => $this->sub->term_id,
            'amount'           => 500,
            'start_date'       => now()->subDays(10)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'status'           => 'active',
            'activated_at'     => now()->subDays(10),
        ]);

        $response = $this->asSuperAdmin()->get('/subscriptions?status=active');

        $response->assertOk();
        // Trial sub should not appear in the active filter
        $activeSubs = $response->viewData('subs');
        foreach ($activeSubs as $s) {
            $this->assertSame('active', $s->status);
        }
    }

    public function test_index_searches_by_school_name(): void
    {
        $response = $this->asSuperAdmin()->get('/subscriptions?search=Demo+School');

        $response->assertOk();
        $response->assertSee('Demo School');
    }

    public function test_index_search_returns_no_results_for_unknown_school(): void
    {
        $response = $this->asSuperAdmin()->get('/subscriptions?search=NonExistentSchoolXYZ');

        $subs = $response->viewData('subs');
        $this->assertSame(0, $subs->total());
    }

    // ── Manual Transitions ────────────────────────────────────────────────────

    public function test_transition_to_active(): void
    {
        $response = $this->asSuperAdmin()->post("/subscriptions/{$this->sub->id}/transition", [
            'action' => 'activate',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->sub->id,
            'status' => 'active',
        ]);
    }

    public function test_transition_to_grace(): void
    {
        $this->asSuperAdmin()->post("/subscriptions/{$this->sub->id}/transition", [
            'action' => 'grace',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->sub->id,
            'status' => 'grace',
        ]);
    }

    public function test_transition_to_locked(): void
    {
        $this->asSuperAdmin()->post("/subscriptions/{$this->sub->id}/transition", [
            'action' => 'lock',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->sub->id,
            'status' => 'locked',
        ]);
    }

    public function test_transition_to_suspended(): void
    {
        $this->asSuperAdmin()->post("/subscriptions/{$this->sub->id}/transition", [
            'action' => 'suspend',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->sub->id,
            'status' => 'suspended',
        ]);
    }

    public function test_transition_rejects_invalid_action(): void
    {
        $response = $this->asSuperAdmin()->post("/subscriptions/{$this->sub->id}/transition", [
            'action' => 'delete',
        ]);

        $response->assertSessionHasErrors('action');
    }

    // ── Extend Grace ──────────────────────────────────────────────────────────

    public function test_extend_grace_puts_subscription_in_grace_and_sets_deadline(): void
    {
        $response = $this->asSuperAdmin()->post("/subscriptions/{$this->sub->id}/extend-grace", [
            'days' => 14,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $fresh = $this->sub->fresh();
        $this->assertSame('grace', $fresh->status);
        $this->assertNotNull($fresh->grace_ends_at);
    }

    public function test_extend_grace_requires_valid_days(): void
    {
        $response = $this->asSuperAdmin()->post("/subscriptions/{$this->sub->id}/extend-grace", [
            'days' => 0,
        ]);

        $response->assertSessionHasErrors('days');
    }

    public function test_extend_grace_rejects_days_over_90(): void
    {
        $response = $this->asSuperAdmin()->post("/subscriptions/{$this->sub->id}/extend-grace", [
            'days' => 91,
        ]);

        $response->assertSessionHasErrors('days');
    }
}
