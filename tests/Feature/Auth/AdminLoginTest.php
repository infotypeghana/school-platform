<?php

namespace Tests\Feature\Auth;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Login / logout for the school-admin subdomain.
 *
 * Routes covered: GET /login, POST /login, POST /logout
 * Middleware exercised: resolve.tenant, guest, throttle
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminUser;
    private string $slug = 'login-school';

    protected function setUp(): void
    {
        parent::setUp();

        // Domain routing works the same way as AdminTestCase.
        URL::forceRootUrl('http://' . $this->slug . '.admin.' . config('app.domain', 'localhost'));
        URL::defaults(['slug' => $this->slug]);

        // Academic calendar (needed so tenant resolve works even without subscription)
        AcademicYear::create(['year_label' => '2024/2025', 'is_current' => true]);

        $this->tenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => $this->slug,
            'name'   => 'Login School',
            'email'  => 'admin@loginschool.edu.gh',
            'status' => 'active',
        ]);

        $this->adminUser = User::factory()->create([
            'role'      => 'school_admin',
            'tenant_id' => $this->tenant->id,
            'password'  => bcrypt('password123'),
        ]);
    }

    // ── Login page ────────────────────────────────────────────────────────────

    public function test_login_page_loads_for_guest(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertViewIs('auth.login');
    }

    public function test_login_page_is_not_accessible_when_authenticated(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/login');

        // Authenticated users are redirected away from guest-only routes
        $response->assertRedirect();
    }

    // ── Successful login ──────────────────────────────────────────────────────

    public function test_correct_credentials_redirect_to_dashboard(): void
    {
        $response = $this->post('/login', [
            'email'    => $this->adminUser->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->adminUser);
    }

    // ── Failed login ──────────────────────────────────────────────────────────

    public function test_wrong_password_shows_validation_error(): void
    {
        $response = $this->post('/login', [
            'email'    => $this->adminUser->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unknown_email_shows_validation_error(): void
    {
        $response = $this->post('/login', [
            'email'    => 'nobody@nowhere.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_school_admin_from_different_tenant_is_denied(): void
    {
        $otherTenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'other-school',
            'name'   => 'Other School',
            'email'  => 'admin@other.edu.gh',
            'status' => 'active',
        ]);

        $otherAdmin = User::factory()->create([
            'role'      => 'school_admin',
            'tenant_id' => $otherTenant->id,
            'password'  => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email'    => $otherAdmin->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_email_is_required(): void
    {
        $response = $this->post('/login', ['password' => 'password123']);

        $response->assertSessionHasErrors('email');
    }

    public function test_password_is_required(): void
    {
        $response = $this->post('/login', ['email' => $this->adminUser->email]);

        $response->assertSessionHasErrors('password');
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_logout_clears_session_and_redirects(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/logout');

        $response->assertRedirect();
        $this->assertGuest();
    }
}
