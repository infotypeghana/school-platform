<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Base class for super-admin HTTP tests.
 *
 * The super-admin portal lives at superadmin.{APP_DOMAIN}.
 * URL::forceRootUrl() is required for the same reason as AdminTestCase —
 * Laravel 13's prepareUrlForRequest() uses url(), not $this->baseUrl.
 */
abstract class SuperAdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://superadmin.' . config('app.domain', 'localhost'));

        $this->superAdmin = User::factory()->create([
            'role'      => 'super_admin',
            'tenant_id' => null,
        ]);
    }

    /** Authenticated super-admin request. */
    protected function asSuperAdmin(): static
    {
        return $this->actingAs($this->superAdmin);
    }

    /** Unauthenticated request (to the superadmin domain). */
    protected function asGuest(): static
    {
        return $this;
    }
}
