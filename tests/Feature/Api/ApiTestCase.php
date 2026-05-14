<?php

namespace Tests\Feature\Api;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base class for REST API tests.
 *
 * Creates a full school environment (tenant, subscription, admin user)
 * and provides helpers to make authenticated API requests.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected AcademicYear $year;
    protected AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

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
            'slug'   => 'test-school',
            'name'   => 'Test School',
            'email'  => 'info@testschool.edu.gh',
            'status' => 'active',
        ]);

        Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $this->year->id,
            'term_id'          => $this->term->id,
            'amount'           => 500,
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'status'           => Subscription::STATUS_ACTIVE,
            'activated_at'     => now()->subDays(60),
        ]);

        $this->user = User::factory()->create([
            'role'      => 'school_admin',
            'tenant_id' => $this->tenant->id,
            'email'     => 'admin@testschool.edu.gh',
            'password'  => bcrypt('Admin@12345'),
        ]);
    }

    /**
     * Return a Sanctum token for the test user.
     */
    protected function token(): string
    {
        return $this->user->createToken('mobile')->plainTextToken;
    }

    /**
     * Make an authenticated API request.
     */
    protected function apiAs(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token())->{$method}($uri, $data);
    }
}
