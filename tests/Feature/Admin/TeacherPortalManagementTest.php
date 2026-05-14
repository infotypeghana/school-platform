<?php

namespace Tests\Feature\Admin;

use App\Models\Teacher;
use Illuminate\Support\Facades\Hash;

/**
 * Tests for admin-side teacher portal management:
 * - Activate portal access with initial password
 * - Deactivate portal access
 * - Reset portal password
 */
class TeacherPortalManagementTest extends AdminTestCase
{
    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = Teacher::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'email'        => 'mrs.mensah@testschool.edu.gh',
            'portal_active' => false,
        ]);
    }

    // ── Activate ─────────────────────────────────────────────────────────────

    public function test_admin_can_activate_teacher_portal(): void
    {
        $response = $this->asAdmin()->post("/teachers/{$this->teacher->id}/portal/activate", [
            'portal_password' => 'Teach@2025',
        ]);

        $response->assertRedirect();

        $this->teacher->refresh();
        $this->assertTrue($this->teacher->portal_active);
        $this->assertTrue(Hash::check('Teach@2025', $this->teacher->portal_password));
    }

    public function test_activate_requires_minimum_6_char_password(): void
    {
        $response = $this->asAdmin()->post("/teachers/{$this->teacher->id}/portal/activate", [
            'portal_password' => '12345', // 5 chars — too short
        ]);

        $response->assertSessionHasErrors('portal_password');
        $this->teacher->refresh();
        $this->assertFalse($this->teacher->portal_active);
    }

    public function test_activate_requires_password_field(): void
    {
        $response = $this->asAdmin()->post("/teachers/{$this->teacher->id}/portal/activate", []);

        $response->assertSessionHasErrors('portal_password');
    }

    public function test_guest_cannot_activate_portal(): void
    {
        $this->asGuest()
            ->post("/teachers/{$this->teacher->id}/portal/activate", ['portal_password' => 'Teach@2025'])
            ->assertRedirect();

        $this->teacher->refresh();
        $this->assertFalse($this->teacher->portal_active);
    }

    // ── Deactivate ───────────────────────────────────────────────────────────

    public function test_admin_can_deactivate_teacher_portal(): void
    {
        // First activate
        $this->teacher->update([
            'portal_active'   => true,
            'portal_password' => Hash::make('SomePass@1'),
        ]);

        $response = $this->asAdmin()->post("/teachers/{$this->teacher->id}/portal/deactivate");

        $response->assertRedirect();
        $this->teacher->refresh();
        $this->assertFalse($this->teacher->portal_active);
    }

    // ── Reset password ────────────────────────────────────────────────────────

    public function test_admin_can_reset_portal_password(): void
    {
        $this->teacher->update([
            'portal_active'   => true,
            'portal_password' => Hash::make('OldPass@1'),
        ]);

        $response = $this->asAdmin()->post("/teachers/{$this->teacher->id}/portal/reset-password", [
            'portal_password' => 'NewPass@2025',
        ]);

        $response->assertRedirect();
        $this->teacher->refresh();
        $this->assertTrue(Hash::check('NewPass@2025', $this->teacher->portal_password));
        $this->assertFalse(Hash::check('OldPass@1', $this->teacher->portal_password));
    }

    public function test_reset_password_requires_minimum_6_chars(): void
    {
        $this->asAdmin()
            ->post("/teachers/{$this->teacher->id}/portal/reset-password", ['portal_password' => 'abc'])
            ->assertSessionHasErrors('portal_password');
    }
}
