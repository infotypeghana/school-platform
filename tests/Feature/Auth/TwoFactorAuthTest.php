<?php

namespace Tests\Feature\Auth;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Two-factor authentication (TOTP) tests.
 *
 * Covers: login interception, challenge form, TOTP verification,
 *         2FA setup flow, disable flow, middleware enforcement.
 */
class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;
    private Google2FA $google2fa;
    private string $slug = 'test-school';

    protected function setUp(): void
    {
        parent::setUp();

        $this->google2fa = new Google2FA();

        $adminRoot = 'http://' . $this->slug . '.admin.' . config('app.domain', 'localhost');
        URL::forceRootUrl($adminRoot);
        URL::defaults(['slug' => $this->slug]);

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

        $this->tenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => $this->slug,
            'name'   => 'Test School',
            'email'  => 'info@testschool.edu.gh',
            'status' => 'active',
        ]);

        Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $year->id,
            'term_id'          => $term->id,
            'amount'           => 500,
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'status'           => Subscription::STATUS_ACTIVE,
            'activated_at'     => now()->subDays(60),
        ]);

        $this->user = User::factory()->create([
            'role'               => 'school_admin',
            'tenant_id'          => $this->tenant->id,
            'email'              => 'admin@testschool.edu.gh',
            'password'           => bcrypt('Admin@12345'),
            'two_factor_enabled' => false,
            'two_factor_secret'  => null,
        ]);
    }

    // ── Login with 2FA disabled (normal flow unchanged) ───────────────────────

    public function test_login_without_2fa_goes_straight_to_dashboard(): void
    {
        $response = $this->post('/login', [
            'email'    => 'admin@testschool.edu.gh',
            'password' => 'Admin@12345',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
    }

    // ── Login with 2FA enabled → redirects to challenge ───────────────────────

    public function test_login_with_2fa_enabled_redirects_to_challenge(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret'  => $secret,
            'two_factor_enabled' => true,
        ]);

        $response = $this->post('/login', [
            'email'    => 'admin@testschool.edu.gh',
            'password' => 'Admin@12345',
        ]);

        $response->assertRedirect('/2fa/challenge');
        // User is NOT authenticated yet
        $this->assertGuest();
        // Pending user stored in session
        $this->assertEquals($this->user->id, session('auth.2fa_pending_user_id'));
    }

    // ── Challenge page ────────────────────────────────────────────────────────

    public function test_challenge_page_requires_pending_session(): void
    {
        // No pending session → redirect to login
        $this->get('/2fa/challenge')->assertRedirect('/login');
    }

    public function test_challenge_page_renders_with_pending_session(): void
    {
        $this->withSession(['auth.2fa_pending_user_id' => $this->user->id])
            ->get('/2fa/challenge')
            ->assertOk()
            ->assertSee('Two-Factor Authentication');
    }

    // ── TOTP code verification ─────────────────────────────────────────────────

    public function test_valid_totp_code_completes_login(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret'  => $secret,
            'two_factor_enabled' => true,
        ]);

        $code = $this->google2fa->getCurrentOtp($secret);

        $response = $this->withSession([
            'auth.2fa_pending_user_id' => $this->user->id,
            'auth.2fa_pending_role'    => 'school_admin',
        ])->post('/2fa/challenge', ['code' => $code]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
        $this->assertTrue(session('auth.2fa_verified'));
    }

    public function test_invalid_totp_code_returns_error(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret'  => $secret,
            'two_factor_enabled' => true,
        ]);

        $response = $this->withSession([
            'auth.2fa_pending_user_id' => $this->user->id,
            'auth.2fa_pending_role'    => 'school_admin',
        ])->post('/2fa/challenge', ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_challenge_without_pending_session_redirects(): void
    {
        $this->post('/2fa/challenge', ['code' => '123456'])
            ->assertRedirect('/login');
    }

    // ── 2FA middleware enforcement ────────────────────────────────────────────

    public function test_2fa_enabled_user_is_blocked_without_verification(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret'  => $secret,
            'two_factor_enabled' => true,
        ]);

        // Logged in but no 2fa_verified session key
        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertRedirect('/2fa/challenge');
    }

    public function test_2fa_enabled_user_with_verified_session_can_access_dashboard(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret'  => $secret,
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($this->user)
            ->withSession(['auth.2fa_verified' => true])
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_2fa_disabled_user_can_access_dashboard_normally(): void
    {
        // 2FA not enabled — no challenge needed
        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk();
    }

    // ── Setup flow ────────────────────────────────────────────────────────────

    public function test_setup_page_is_accessible_to_logged_in_user(): void
    {
        $this->actingAs($this->user)
            ->get('/settings/2fa')
            ->assertOk()
            ->assertSee('Enable Two-Factor Authentication');
    }

    public function test_setup_generates_session_secret(): void
    {
        $this->actingAs($this->user)->get('/settings/2fa');
        $this->assertNotNull(session('2fa_setup_secret'));
    }

    public function test_valid_confirm_enables_2fa(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $code   = $this->google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($this->user)
            ->withSession(['2fa_setup_secret' => $secret])
            ->post('/settings/2fa/confirm', ['code' => $code]);

        $response->assertRedirect('/settings/account');
        $this->user->refresh();
        $this->assertTrue($this->user->two_factor_enabled);
        $this->assertNotNull($this->user->two_factor_secret);
    }

    public function test_wrong_confirm_code_does_not_enable_2fa(): void
    {
        $secret = $this->google2fa->generateSecretKey();

        $this->actingAs($this->user)
            ->withSession(['2fa_setup_secret' => $secret])
            ->post('/settings/2fa/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->user->refresh();
        $this->assertFalse($this->user->two_factor_enabled);
    }

    // ── Disable flow ──────────────────────────────────────────────────────────

    public function test_disable_requires_correct_password(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret'  => $secret,
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($this->user)
            ->withSession(['auth.2fa_verified' => true])
            ->post('/settings/2fa/disable', ['password' => 'WrongPass!'])
            ->assertSessionHasErrors('password');

        $this->user->refresh();
        $this->assertTrue($this->user->two_factor_enabled);
    }

    public function test_correct_password_disables_2fa(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret'  => $secret,
            'two_factor_enabled' => true,
            'password'           => bcrypt('Admin@12345'),
        ]);

        $this->actingAs($this->user)
            ->withSession(['auth.2fa_verified' => true])
            ->post('/settings/2fa/disable', ['password' => 'Admin@12345'])
            ->assertRedirect('/settings/account');

        $this->user->refresh();
        $this->assertFalse($this->user->two_factor_enabled);
        $this->assertNull($this->user->two_factor_secret);
    }
}
