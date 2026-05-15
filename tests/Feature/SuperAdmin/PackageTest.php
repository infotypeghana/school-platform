<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\SubscriptionPackage;
use App\Models\Subscription;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Tenant;
use Illuminate\Support\Str;

class PackageTest extends SuperAdminTestCase
{
    private function makePackage(array $overrides = []): SubscriptionPackage
    {
        return SubscriptionPackage::create(array_merge([
            'name'              => 'Starter',
            'slug'              => 'starter',
            'price_per_student' => 8.00,
            'min_students'      => 50,
            'billing_cycle'     => 'term',
            'is_active'         => true,
            'sort_order'        => 1,
        ], $overrides));
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_packages_index_returns_200(): void
    {
        $this->asSuperAdmin()->get('/packages')
            ->assertOk()
            ->assertViewIs('superadmin.packages.index');
    }

    public function test_packages_index_shows_packages(): void
    {
        $this->makePackage();
        $this->asSuperAdmin()->get('/packages')
            ->assertOk()
            ->assertSee('Starter');
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function test_create_page_returns_200(): void
    {
        $this->asSuperAdmin()->get('/packages/create')
            ->assertOk()
            ->assertViewIs('superadmin.packages.create');
    }

    public function test_super_admin_can_create_package(): void
    {
        $this->asSuperAdmin()->post('/packages', [
            'name'              => 'Growth',
            'price_per_student' => 6.00,
            'min_students'      => 100,
            'billing_cycle'     => 'term',
            'is_active'         => true,
            'sort_order'        => 2,
        ])->assertRedirect('/packages')
          ->assertSessionHas('success');

        $this->assertDatabaseHas('subscription_packages', [
            'name'              => 'Growth',
            'slug'              => 'growth',
            'price_per_student' => '6.00',
            'min_students'      => 100,
        ]);
    }

    public function test_store_requires_name_and_price(): void
    {
        $this->asSuperAdmin()->post('/packages', [])
            ->assertSessionHasErrors(['name', 'price_per_student']);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        $this->makePackage(['name' => 'Starter', 'slug' => 'starter']);

        $this->asSuperAdmin()->post('/packages', [
            'name'              => 'Starter',
            'price_per_student' => 9.00,
            'min_students'      => 50,
            'billing_cycle'     => 'term',
            'is_active'         => true,
            'sort_order'        => 5,
        ])->assertSessionHasErrors('name');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function test_edit_page_returns_200(): void
    {
        $pkg = $this->makePackage();
        $this->asSuperAdmin()->get("/packages/{$pkg->id}/edit")
            ->assertOk()
            ->assertViewIs('superadmin.packages.edit');
    }

    public function test_super_admin_can_update_package(): void
    {
        $pkg = $this->makePackage();

        $this->asSuperAdmin()->put("/packages/{$pkg->id}", [
            'name'              => 'Starter Plus',
            'price_per_student' => 9.50,
            'min_students'      => 60,
            'billing_cycle'     => 'term',
            'is_active'         => true,
            'sort_order'        => 1,
        ])->assertRedirect('/packages')
          ->assertSessionHas('success');

        $this->assertDatabaseHas('subscription_packages', [
            'id'                => $pkg->id,
            'name'              => 'Starter Plus',
            'price_per_student' => '9.50',
        ]);
    }

    public function test_features_are_stored_as_array(): void
    {
        $this->asSuperAdmin()->post('/packages', [
            'name'              => 'Premium',
            'price_per_student' => 12.00,
            'min_students'      => 50,
            'billing_cycle'     => 'term',
            'is_active'         => true,
            'sort_order'        => 3,
            'features'          => "Attendance\nReport Cards\nSMS",
        ]);

        $pkg = SubscriptionPackage::where('name', 'Premium')->first();
        $this->assertIsArray($pkg->features);
        $this->assertContains('Attendance', $pkg->features);
        $this->assertContains('Report Cards', $pkg->features);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_super_admin_can_delete_unused_package(): void
    {
        $pkg = $this->makePackage();

        $this->asSuperAdmin()->delete("/packages/{$pkg->id}")
            ->assertRedirect('/packages')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('subscription_packages', ['id' => $pkg->id]);
    }

    public function test_cannot_delete_package_with_subscriptions(): void
    {
        $pkg = $this->makePackage();

        // Create a subscription referencing this package
        $year = AcademicYear::create(['year_label' => 'Pkg-Test-Year', 'is_current' => false]);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'term_number'      => 1,
            'term_name'        => 'Term 1',
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(90)->toDateString(),
            'is_current'       => false,
        ]);
        $tenant = Tenant::create([
            'uuid' => Str::uuid(), 'slug' => 'pkg-test-school',
            'name' => 'Pkg Test School', 'email' => 'pkg@test.edu.gh', 'status' => 'active',
        ]);
        Subscription::create([
            'tenant_id'        => $tenant->id,
            'academic_year_id' => $year->id,
            'term_id'          => $term->id,
            'package_id'       => $pkg->id,
            'amount'           => 400,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(90)->toDateString(),
            'status'           => 'active',
        ]);

        $this->asSuperAdmin()->delete("/packages/{$pkg->id}")
            ->assertRedirect('/packages')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('subscription_packages', ['id' => $pkg->id]);
    }

    // ── Calculate amount ──────────────────────────────────────────────────────

    public function test_calculate_amount_respects_minimum_students(): void
    {
        $pkg = $this->makePackage([
            'price_per_student' => 8.00,
            'min_students'      => 50,
        ]);

        // Below minimum — billed as 50
        $this->assertEquals(400.00, $pkg->calculateAmount(30));

        // At minimum
        $this->assertEquals(400.00, $pkg->calculateAmount(50));

        // Above minimum
        $this->assertEquals(960.00, $pkg->calculateAmount(120));
    }

    // ── Auth guard ────────────────────────────────────────────────────────────

    public function test_guest_redirected_from_packages(): void
    {
        $this->asGuest()->get('/packages')->assertRedirect();
    }
}
