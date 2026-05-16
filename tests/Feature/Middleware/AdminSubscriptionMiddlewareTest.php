<?php

namespace Tests\Feature\Middleware;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for AdminSubscriptionMiddleware.
 *
 * Verifies that:
 *   - trial / active tenants pass through to the controller
 *   - grace tenants pass through but share grace data with views
 *   - locked / suspended / none tenants see the lock screen (403)
 */
class AdminSubscriptionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;
    private AcademicTerm $term;

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
    }

    public function test_trial_tenant_passes_through(): void
    {
        [$user] = $this->scaffoldTenant('trial');

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_active_tenant_passes_through(): void
    {
        [$user] = $this->scaffoldTenant('active');

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_grace_tenant_passes_through(): void
    {
        [$user] = $this->scaffoldTenant('grace');

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_locked_tenant_shows_lock_screen(): void
    {
        [$user] = $this->scaffoldTenant('locked');

        $this->actingAs($user)->get('/dashboard')->assertStatus(403);
    }

    public function test_suspended_tenant_shows_lock_screen(): void
    {
        [$user] = $this->scaffoldTenant('suspended');

        $this->actingAs($user)->get('/dashboard')->assertStatus(403);
    }

    public function test_tenant_with_no_subscription_shows_lock_screen(): void
    {
        $tenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'nosub-school',
            'name'   => 'No Sub School',
            'email'  => 'admin@nosub.edu.gh',
            'status' => 'pending',
        ]);

        $user = User::factory()->create([
            'role'      => 'school_admin',
            'tenant_id' => $tenant->id,
        ]);

        URL::forceRootUrl('http://nosub-school.admin.' . config('app.domain', 'localhost'));
        URL::defaults(['slug' => 'nosub-school']);

        $this->actingAs($user)->get('/dashboard')->assertStatus(403);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @return array{User, Tenant, Subscription}
     */
    private function scaffoldTenant(string $subscriptionStatus): array
    {
        $slug = Str::slug($subscriptionStatus . '-' . Str::random(4));

        $tenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => $slug,
            'name'   => ucfirst($subscriptionStatus) . ' School',
            'email'  => "admin@{$slug}.edu.gh",
            'status' => in_array($subscriptionStatus, ['trial', 'active', 'grace']) ? $subscriptionStatus : 'locked',
        ]);

        // Map subscription status to Tenant status for middleware cache
        $tenantStatus = match ($subscriptionStatus) {
            'trial'     => 'trial',
            'active'    => 'active',
            'grace'     => 'grace',
            'locked'    => 'locked',
            'suspended' => 'suspended',
            default     => 'locked',
        };
        $tenant->update(['status' => $tenantStatus]);

        Subscription::create([
            'tenant_id'        => $tenant->id,
            'academic_year_id' => $this->year->id,
            'term_id'          => $this->term->id,
            'amount'           => 500,
            'start_date'       => now()->subDays(30)->toDateString(),
            'end_date'         => now()->addDays(60)->toDateString(),
            'status'           => $subscriptionStatus,
            'grace_ends_at'    => $subscriptionStatus === 'grace' ? now()->addDays(3) : null,
            'activated_at'     => now()->subDays(30),
        ]);

        $user = User::factory()->create([
            'role'      => 'school_admin',
            'tenant_id' => $tenant->id,
        ]);

        URL::forceRootUrl("http://{$slug}.admin." . config('app.domain', 'localhost'));
        URL::defaults(['slug' => $slug]);

        return [$user, $tenant];
    }
}
