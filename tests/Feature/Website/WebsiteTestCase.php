<?php

namespace Tests\Feature\Website;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base class for public school website HTTP tests.
 *
 * Middleware stack: resolve.tenant → subscription.website
 *
 * Domain: {slug}.{APP_DOMAIN}  (no "admin" segment — plain school website).
 * URL::forceRootUrl() is required for the same reason as AdminTestCase.
 */
abstract class WebsiteTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected AcademicYear $year;
    protected AcademicTerm $term;
    protected Subscription $subscription;

    protected string $slug = 'website-school';

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://' . $this->slug . '.' . config('app.domain', 'localhost'));
        URL::defaults(['slug' => $this->slug]);

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

        $this->tenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => $this->slug,
            'name'   => 'Website School',
            'email'  => 'admin@websiteschool.edu.gh',
            'status' => 'active',
        ]);

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
    }
}
