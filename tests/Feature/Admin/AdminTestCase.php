<?php

namespace Tests\Feature\Admin;

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
 * Base class for all school-admin HTTP tests.
 *
 * Middleware stack covered:
 *   ResolveTenantMiddleware → EnsureSchoolAdmin → AdminSubscriptionMiddleware
 *
 * Domain routing in tests
 * ───────────────────────
 * Laravel 13's MakesHttpRequests::prepareUrlForRequest() uses url($uri), not
 * $this->baseUrl, so plain get('/dashboard') calls url('dashboard') which
 * resolves to http://localhost/dashboard — never matching the domain group.
 *
 * Fix: call URL::forceRootUrl() to redirect the URL helper to the tenant's
 * admin subdomain, making url('dashboard') → http://test-school.admin.<domain>/dashboard.
 * SymfonyRequest::create() then parses the host correctly and the router matches.
 */
abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected AcademicYear $year;
    protected AcademicTerm $term;
    protected Subscription $subscription;

    /** Slug used for the fake subdomain. Must match the tenant created below. */
    protected string $slug = 'test-school';

    protected function setUp(): void
    {
        parent::setUp();

        // Force the URL generator to produce fully-qualified admin-subdomain URLs.
        // config('app.domain') is 'schoolms.com.gh' from .env (same value the
        // route group was registered with), so the domain route matches.
        $adminRoot = 'http://' . $this->slug . '.admin.' . config('app.domain', 'localhost');
        URL::forceRootUrl($adminRoot);

        // Domain route groups bind {slug} as a route parameter. Every route()
        // call inside Blade views requires it. Setting a default means
        // route('admin.teachers.create') generates the correct URL without
        // needing slug passed explicitly everywhere.
        URL::defaults(['slug' => $this->slug]);

        // 1. Academic calendar
        $this->year = AcademicYear::create([
            'year_label' => '2024/2025',
            'is_current' => true,
        ]);

        $this->term = AcademicTerm::create([
            'academic_year_id' => $this->year->id,
            'term_number'      => 1,
            'term_name'        => 'First Term',
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'is_current'       => true,
        ]);

        // 2. Tenant with a predictable slug (matches the subdomain above)
        $this->tenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => $this->slug,
            'name'   => 'Test School',
            'email'  => 'admin@testschool.edu.gh',
            'status' => 'active',
        ]);

        // 3. Active subscription — required by AdminSubscriptionMiddleware
        $this->subscription = Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $this->year->id,
            'term_id'          => $this->term->id,
            'amount'           => 500,
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'status'           => Subscription::STATUS_ACTIVE,
            'activated_at'     => now()->subDays(60),
        ]);

        // 4. School admin user for this tenant
        $this->user = User::factory()->create([
            'role'      => 'school_admin',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** Authenticated admin — host is already correct via forceRootUrl. */
    protected function asAdmin(): static
    {
        return $this->actingAs($this->user);
    }

    /** Unauthenticated — host still correct, just no session user. */
    protected function asGuest(): static
    {
        return $this;
    }
}
