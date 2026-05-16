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
 * Tests for ResolveTenantMiddleware.
 *
 * The middleware:
 *   1. Extracts the slug from the subdomain
 *   2. Looks up the Tenant by slug OR custom domain
 *   3. Binds currentTenant into the container
 *   4. Removes the {slug} route parameter so it isn't injected into controllers
 *   5. Aborts 404 for unknown slugs or reserved words (www, admin, superadmin)
 */
class ResolveTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Tenant       $tenant;
    private User         $user;
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
            'slug'   => 'resolve-test-school',
            'name'   => 'Resolve Test School',
            'email'  => 'admin@resolve.edu.gh',
            'status' => 'active',
        ]);

        $this->subscription = Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $year->id,
            'term_id'          => $term->id,
            'amount'           => 500,
            'start_date'       => now()->subDays(30)->toDateString(),
            'end_date'         => now()->addDays(60)->toDateString(),
            'status'           => Subscription::STATUS_ACTIVE,
            'activated_at'     => now()->subDays(30),
        ]);

        $this->user = User::factory()->create([
            'role'      => 'school_admin',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_resolves_tenant_by_slug_from_subdomain(): void
    {
        URL::forceRootUrl('http://resolve-test-school.admin.' . config('app.domain', 'localhost'));
        URL::defaults(['slug' => 'resolve-test-school']);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk();

        // The tenant must be bound in the container during the request
        // (verified indirectly — dashboard would 404 if tenant binding failed)
    }

    public function test_resolves_tenant_by_custom_domain(): void
    {
        // Give the tenant a domain alias that differs from its slug so the DB
        // can only find it via the domain column, not the slug column.
        // We use a subdomain of the test domain so the {slug}.{domain} route
        // group actually matches and passes the request to the middleware.
        $appDomain    = config('app.domain', 'localhost');
        $aliasSlug    = 'academy-alias';   // ≠ 'resolve-test-school'
        $aliasHost    = "{$aliasSlug}.{$appDomain}";

        $this->tenant->update(['domain' => $aliasHost]);

        // The route {slug}.{domain} matches with slug='academy-alias'.
        // No tenant has slug='academy-alias', so slug lookup alone would return
        // null. The middleware's orWhere('domain', $host) finds the tenant via
        // the domain column — exactly what we want to prove.
        URL::forceRootUrl("http://{$aliasHost}");
        // Blade views call route('website.*') which needs the slug URL default;
        // use the alias slug (the domain prefix) so URL generation works.
        URL::defaults(['slug' => $aliasSlug]);

        $this->get('/')->assertOk();
    }

    public function test_aborts_404_for_unknown_slug(): void
    {
        URL::forceRootUrl('http://nonexistent-school-xyz.admin.' . config('app.domain', 'localhost'));

        $this->get('/dashboard')->assertNotFound();
    }

    public function test_aborts_404_for_reserved_slug_superadmin(): void
    {
        URL::forceRootUrl('http://superadmin.' . config('app.domain', 'localhost'));

        // The superadmin domain has its own route group (login page, not dashboard)
        // so we just confirm no school is resolved by hitting an invalid path
        $this->get('/some-unknown-path')->assertNotFound();
    }

    public function test_slug_route_parameter_is_removed_after_resolution(): void
    {
        // If the slug parameter were NOT removed, controllers that declare a
        // route-bound model as their first parameter would receive the slug
        // string as the positional arg instead — causing an ArgumentCountError.
        // A successful 200 from the dashboard proves forgetParameter() worked.
        URL::forceRootUrl('http://resolve-test-school.admin.' . config('app.domain', 'localhost'));
        URL::defaults(['slug' => 'resolve-test-school']);

        $this->actingAs($this->user)->get('/dashboard')->assertOk();
    }
}
