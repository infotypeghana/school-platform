<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Hash;

class SettingsTest extends AdminTestCase
{
    // ── School Profile ────────────────────────────────────────────────────────

    public function test_school_settings_page_returns_200(): void
    {
        $response = $this->asAdmin()->get('/settings');

        $response->assertOk();
        $response->assertViewIs('admin.settings.school');
    }

    public function test_school_settings_shows_tenant_name(): void
    {
        $response = $this->asAdmin()->get('/settings');

        $response->assertSee($this->tenant->name);
    }

    public function test_guest_redirected_from_school_settings(): void
    {
        $this->asGuest()->get('/settings')->assertRedirect();
    }

    public function test_update_school_profile_saves_name(): void
    {
        $response = $this->asAdmin()->put('/settings', [
            'name' => 'Renamed School',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tenants', [
            'id'   => $this->tenant->id,
            'name' => 'Renamed School',
        ]);
    }

    public function test_update_school_profile_saves_contact_info(): void
    {
        $this->asAdmin()->put('/settings', [
            'name'          => $this->tenant->name,
            'contact_phone' => '030-111-2222',
            'contact_email' => 'billing@testschool.edu.gh',
            'address'       => '12 Main Street, Accra',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id'            => $this->tenant->id,
            'contact_phone' => '030-111-2222',
            'contact_email' => 'billing@testschool.edu.gh',
            'address'       => '12 Main Street, Accra',
        ]);
    }

    public function test_update_school_profile_requires_name(): void
    {
        $response = $this->asAdmin()->put('/settings', []);

        $response->assertSessionHasErrors('name');
    }

    public function test_update_school_profile_validates_contact_email(): void
    {
        $response = $this->asAdmin()->put('/settings', [
            'name'          => $this->tenant->name,
            'contact_email' => 'not-a-valid-email',
        ]);

        $response->assertSessionHasErrors('contact_email');
    }

    public function test_update_school_profile_validates_primary_colour(): void
    {
        $response = $this->asAdmin()->put('/settings', [
            'name'          => $this->tenant->name,
            'primary_color' => 'badcolor',
        ]);

        $response->assertSessionHasErrors('primary_color');
    }

    public function test_update_school_profile_accepts_valid_hex_colour(): void
    {
        $response = $this->asAdmin()->put('/settings', [
            'name'          => $this->tenant->name,
            'primary_color' => '#1e40af',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tenants', [
            'id'            => $this->tenant->id,
            'primary_color' => '#1e40af',
        ]);
    }

    // ── Account Settings ──────────────────────────────────────────────────────

    public function test_account_settings_page_returns_200(): void
    {
        $response = $this->asAdmin()->get('/settings/account');

        $response->assertOk();
        $response->assertViewIs('admin.settings.account');
    }

    public function test_guest_redirected_from_account_settings(): void
    {
        $this->asGuest()->get('/settings/account')->assertRedirect();
    }

    public function test_update_password_succeeds_with_correct_current_password(): void
    {
        // The factory default password is 'password'
        $response = $this->asAdmin()->put('/settings/account', [
            'current_password'      => 'password',
            'password'              => 'NewSecure@123',
            'password_confirmation' => 'NewSecure@123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue(Hash::check('NewSecure@123', $this->user->fresh()->password));
    }

    public function test_update_password_fails_with_wrong_current_password(): void
    {
        $response = $this->asAdmin()->put('/settings/account', [
            'current_password'      => 'wrong-password',
            'password'              => 'NewSecure@123',
            'password_confirmation' => 'NewSecure@123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_update_password_requires_confirmation(): void
    {
        $response = $this->asAdmin()->put('/settings/account', [
            'current_password'      => 'password',
            'password'              => 'NewSecure@123',
            'password_confirmation' => 'DifferentPassword@123',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_update_password_requires_minimum_length(): void
    {
        $response = $this->asAdmin()->put('/settings/account', [
            'current_password'      => 'password',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
