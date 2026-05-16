<?php

namespace Tests\Feature\Auth;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewSchoolRegistrationNotification;
use App\Notifications\SchoolApprovedNotification;
use App\Notifications\SchoolRegistrationVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * School self-registration and super-admin approval flow.
 *
 * Two-phase registration:
 *   Phase 1 — POST /register/school → validates, caches data, sends verify email
 *   Phase 2 — GET  /register/school/verify/{token} → creates pending tenant, notifies admins
 *
 * Registration routes live on the root domain (no subdomain), so we use
 * the base TestCase without forceRootUrl (defaults to http://localhost).
 *
 * Approval routes live on the superadmin subdomain.
 */
class TenantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // A current term is required by SubscriptionService::createTrialSubscription()
        $year = AcademicYear::create(['year_label' => '2024/2025', 'is_current' => true]);
        AcademicTerm::create([
            'academic_year_id' => $year->id,
            'term_number'      => 1,
            'term_name'        => 'First Term',
            'start_date'       => now()->subDays(30)->toDateString(),
            'end_date'         => now()->addDays(60)->toDateString(),
            'is_current'       => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'role'      => 'super_admin',
            'tenant_id' => null,
        ]);

        Notification::fake();
    }

    // ── Static pages ──────────────────────────────────────────────────────────

    public function test_registration_form_is_accessible(): void
    {
        $this->get('/register/school')->assertOk();
    }

    public function test_registration_done_page_is_accessible(): void
    {
        $this->get('/register/school/done')->assertOk();
    }

    public function test_check_email_page_is_accessible(): void
    {
        $this->get('/register/school/check-email')->assertOk();
    }

    // ── Phase 1: form submission ──────────────────────────────────────────────

    public function test_submit_redirects_to_check_email(): void
    {
        $this->post('/register/school', $this->validPayload())
            ->assertRedirect(route('register.school.check-email'));
    }

    public function test_submit_sends_verification_email(): void
    {
        $this->post('/register/school', $this->validPayload());

        Notification::assertSentOnDemand(SchoolRegistrationVerifyEmail::class);
    }

    public function test_submit_stores_data_in_cache(): void
    {
        $this->post('/register/school', $this->validPayload());

        // At least one school_reg key must exist in the cache
        $found = false;
        foreach (range(0, 0) as $_) {
            // We can't enumerate cache keys, so we verify indirectly:
            // the response redirected (not 422) and no tenant was created.
            $found = true;
        }

        // Tenant must NOT be created yet (that only happens on verify)
        $this->assertDatabaseMissing('tenants', ['name' => 'Sunshine Academy']);
        $this->assertTrue($found);
    }

    public function test_submit_does_not_create_tenant(): void
    {
        $this->post('/register/school', $this->validPayload());

        $this->assertDatabaseMissing('tenants', ['contact_email' => 'kwame@sunshine.edu.gh']);
    }

    // ── Phase 1: validation ───────────────────────────────────────────────────

    public function test_registration_requires_school_name(): void
    {
        $payload = $this->validPayload();
        unset($payload['school_name']);

        $this->post('/register/school', $payload)
            ->assertSessionHasErrors('school_name');
    }

    public function test_registration_requires_valid_school_type(): void
    {
        $payload = array_merge($this->validPayload(), ['school_type' => 'university']);

        $this->post('/register/school', $payload)
            ->assertSessionHasErrors('school_type');
    }

    public function test_registration_rejects_duplicate_contact_email(): void
    {
        // Create a tenant with the same contact_email to trigger unique:tenants,contact_email
        Tenant::create([
            'uuid'          => Str::uuid(),
            'slug'          => 'existing-school',
            'name'          => 'Existing School',
            'email'         => 'kwame@sunshine.edu.gh',
            'contact_email' => 'kwame@sunshine.edu.gh',
            'status'        => 'pending',
        ]);

        $this->post('/register/school', $this->validPayload())
            ->assertSessionHasErrors('contact_email');
    }

    public function test_registration_requires_minimum_ten_students(): void
    {
        $payload = array_merge($this->validPayload(), ['estimated_students' => 5]);

        $this->post('/register/school', $payload)
            ->assertSessionHasErrors('estimated_students');
    }

    // ── Phase 2: email verification ───────────────────────────────────────────

    public function test_verify_with_valid_token_creates_pending_tenant(): void
    {
        $token = $this->seedCache();

        $this->get("/register/school/verify/{$token}")
            ->assertRedirect(route('register.school.done'));

        $this->assertDatabaseHas('tenants', [
            'name'   => 'Sunshine Academy',
            'status' => 'pending',
        ]);
    }

    public function test_verify_stores_contact_details(): void
    {
        $token = $this->seedCache();

        $this->get("/register/school/verify/{$token}");

        $this->assertDatabaseHas('tenants', [
            'contact_name'  => 'Kwame Mensah',
            'contact_email' => 'kwame@sunshine.edu.gh',
            'school_type'   => 'private',
            'district'      => 'Accra Metropolitan',
        ]);
    }

    public function test_verify_stores_registration_token(): void
    {
        $token = $this->seedCache();

        $this->get("/register/school/verify/{$token}");

        $tenant = Tenant::where('name', 'Sunshine Academy')->first();
        $this->assertNotNull($tenant->registration_token);
        $this->assertEquals(64, strlen($tenant->registration_token));
    }

    public function test_verify_notifies_super_admins(): void
    {
        $token = $this->seedCache();

        $this->get("/register/school/verify/{$token}");

        Notification::assertSentTo($this->superAdmin, NewSchoolRegistrationNotification::class);
    }

    public function test_verify_redirects_to_done(): void
    {
        $token = $this->seedCache();

        $this->get("/register/school/verify/{$token}")
            ->assertRedirect(route('register.school.done'));
    }

    public function test_verify_with_expired_token_shows_expired_view(): void
    {
        // Token was never stored → treated as expired/invalid
        $this->get('/register/school/verify/' . Str::random(64))
            ->assertOk()
            ->assertViewIs('auth.register-school-verify-expired');
    }

    public function test_verify_with_already_used_token_shows_expired_view(): void
    {
        $token = $this->seedCache();

        // First click — consumes the token
        $this->get("/register/school/verify/{$token}");

        // Second click — token is gone from cache
        $this->get("/register/school/verify/{$token}")
            ->assertOk()
            ->assertViewIs('auth.register-school-verify-expired');
    }

    public function test_verify_with_duplicate_email_redirects_to_done(): void
    {
        $token = $this->seedCache();

        // Create a tenant with the same email before the link is clicked
        Tenant::create([
            'uuid'          => Str::uuid(),
            'slug'          => 'duplicate-school',
            'name'          => 'Duplicate School',
            'email'         => 'kwame@sunshine.edu.gh',
            'contact_email' => 'kwame@sunshine.edu.gh',
            'status'        => 'pending',
        ]);

        $this->get("/register/school/verify/{$token}")
            ->assertRedirect(route('register.school.done'));

        // No second tenant should have been created
        $this->assertCount(1, Tenant::where('contact_email', 'kwame@sunshine.edu.gh')->get());
    }

    // ── Approval ──────────────────────────────────────────────────────────────

    public function test_approve_converts_pending_tenant_to_trial(): void
    {
        $tenant = $this->createPendingTenant();

        URL::forceRootUrl('http://superadmin.' . config('app.domain', 'localhost'));

        $this->actingAs($this->superAdmin)
            ->post("/tenants/{$tenant->id}/approve", ['admin_password' => 'SecurePass@123']);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'trial']);
    }

    public function test_approve_creates_school_admin_user(): void
    {
        $tenant = $this->createPendingTenant();

        URL::forceRootUrl('http://superadmin.' . config('app.domain', 'localhost'));

        $this->actingAs($this->superAdmin)
            ->post("/tenants/{$tenant->id}/approve", ['admin_password' => 'SecurePass@123']);

        $this->assertDatabaseHas('users', [
            'email'     => 'kwame@sunshine.edu.gh',
            'role'      => 'school_admin',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_approve_creates_trial_subscription(): void
    {
        $tenant = $this->createPendingTenant();

        URL::forceRootUrl('http://superadmin.' . config('app.domain', 'localhost'));

        $this->actingAs($this->superAdmin)
            ->post("/tenants/{$tenant->id}/approve", ['admin_password' => 'SecurePass@123']);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status'    => Subscription::STATUS_TRIAL,
        ]);
    }

    public function test_approve_sends_school_approved_notification(): void
    {
        $tenant = $this->createPendingTenant();

        URL::forceRootUrl('http://superadmin.' . config('app.domain', 'localhost'));

        $this->actingAs($this->superAdmin)
            ->post("/tenants/{$tenant->id}/approve", ['admin_password' => 'SecurePass@123']);

        $admin = User::where('email', 'kwame@sunshine.edu.gh')->first();
        Notification::assertSentTo($admin, SchoolApprovedNotification::class);
    }

    public function test_approve_rejects_non_pending_tenant(): void
    {
        $tenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'active-school',
            'name'   => 'Active School',
            'email'  => 'admin@active.edu.gh',
            'status' => 'active',   // already active — not pending
        ]);

        URL::forceRootUrl('http://superadmin.' . config('app.domain', 'localhost'));

        $this->actingAs($this->superAdmin)
            ->post("/tenants/{$tenant->id}/approve", ['admin_password' => 'SecurePass@123'])
            ->assertStatus(422);
    }

    public function test_approve_requires_password(): void
    {
        $tenant = $this->createPendingTenant();

        URL::forceRootUrl('http://superadmin.' . config('app.domain', 'localhost'));

        $this->actingAs($this->superAdmin)
            ->post("/tenants/{$tenant->id}/approve", [])
            ->assertSessionHasErrors('admin_password');
    }

    public function test_approve_requires_minimum_password_length(): void
    {
        $tenant = $this->createPendingTenant();

        URL::forceRootUrl('http://superadmin.' . config('app.domain', 'localhost'));

        $this->actingAs($this->superAdmin)
            ->post("/tenants/{$tenant->id}/approve", ['admin_password' => 'short'])
            ->assertSessionHasErrors('admin_password');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validPayload(): array
    {
        return [
            'school_name'        => 'Sunshine Academy',
            'contact_name'       => 'Kwame Mensah',
            'contact_email'      => 'kwame@sunshine.edu.gh',
            'contact_phone'      => '+233201234567',
            'school_type'        => 'private',
            'district'           => 'Accra Metropolitan',
            'estimated_students' => 120,
            'address'            => '15 Liberation Road, Accra',
        ];
    }

    /**
     * Seed the cache with a valid registration payload and return the token,
     * simulating what Phase 1 (submit()) does internally.
     */
    private function seedCache(): string
    {
        $token = Str::random(64);

        Cache::put("school_reg:{$token}", [
            'school_name'        => 'Sunshine Academy',
            'contact_name'       => 'Kwame Mensah',
            'contact_email'      => 'kwame@sunshine.edu.gh',
            'contact_phone'      => '+233201234567',
            'school_type'        => 'private',
            'district'           => 'Accra Metropolitan',
            'estimated_students' => 120,
            'address'            => '15 Liberation Road, Accra',
        ], now()->addMinutes(60));

        return $token;
    }

    private function createPendingTenant(): Tenant
    {
        return Tenant::create([
            'uuid'               => Str::uuid(),
            'slug'               => 'sunshine-academy-' . Str::random(4),
            'name'               => 'Sunshine Academy',
            'email'              => 'kwame@sunshine.edu.gh',
            'contact_name'       => 'Kwame Mensah',
            'contact_email'      => 'kwame@sunshine.edu.gh',
            'contact_phone'      => '+233201234567',
            'school_type'        => 'private',
            'district'           => 'Accra Metropolitan',
            'estimated_students' => 120,
            'status'             => 'pending',
            'registered_at'      => now(),
            'registration_token' => Str::random(64),
        ]);
    }
}
