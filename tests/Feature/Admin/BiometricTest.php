<?php

namespace Tests\Feature\Admin;

use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\BiometricLog;
use App\Models\SchoolClass;
use App\Models\Student;

class BiometricTest extends AdminTestCase
{
    private BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = BiometricDevice::create([
            'tenant_id'     => $this->tenant->id,
            'name'          => 'Main Gate',
            'device_serial' => 'SN-TEST-001',
            'ip_address'    => '192.168.1.100',
            'port'          => 4370,
            'is_active'     => true,
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $this->asAdmin()->get('/biometric')
            ->assertOk()
            ->assertViewIs('admin.biometric.index');
    }

    public function test_index_shows_device(): void
    {
        $this->asAdmin()->get('/biometric')->assertSee('Main Gate');
    }

    public function test_guest_redirected(): void
    {
        $this->asGuest()->get('/biometric')->assertRedirect();
    }

    // ── Device CRUD ───────────────────────────────────────────────────────────

    public function test_create_form_returns_200(): void
    {
        $this->asAdmin()->get('/biometric/create')
            ->assertOk()
            ->assertViewIs('admin.biometric.form');
    }

    public function test_store_registers_device(): void
    {
        $this->asAdmin()->post('/biometric', [
            'name'          => 'Staff Room Scanner',
            'device_serial' => 'SN-STAFF-001',
            'ip_address'    => '192.168.1.102',
            'port'          => 4370,
            'is_active'     => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('biometric_devices', ['name' => 'Staff Room Scanner']);
    }

    public function test_store_validates_required_name(): void
    {
        $this->asAdmin()->post('/biometric', [])
            ->assertSessionHasErrors('name');
    }

    public function test_store_validates_ip_format(): void
    {
        $this->asAdmin()->post('/biometric', [
            'name'       => 'Bad IP',
            'ip_address' => 'not-an-ip',
        ])->assertSessionHasErrors('ip_address');
    }

    public function test_edit_returns_200(): void
    {
        $this->asAdmin()->get("/biometric/{$this->device->id}/edit")
            ->assertOk()
            ->assertViewIs('admin.biometric.form');
    }

    public function test_update_changes_name(): void
    {
        $this->asAdmin()->put("/biometric/{$this->device->id}", [
            'name'      => 'Updated Gate',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('biometric_devices', ['id' => $this->device->id, 'name' => 'Updated Gate']);
    }

    public function test_destroy_removes_device(): void
    {
        $this->asAdmin()->delete("/biometric/{$this->device->id}")->assertRedirect();
        $this->assertDatabaseMissing('biometric_devices', ['id' => $this->device->id]);
    }

    // ── Enrollment ────────────────────────────────────────────────────────────

    public function test_enroll_page_returns_200(): void
    {
        $this->asAdmin()->get("/biometric/{$this->device->id}/enroll")
            ->assertOk()
            ->assertViewIs('admin.biometric.enroll');
    }

    public function test_store_enrollment_maps_student(): void
    {
        $class   = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);
        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
        ]);

        $this->asAdmin()->post("/biometric/{$this->device->id}/enroll", [
            'device_user_id' => '42',
            'person_type'    => 'student',
            'person_id'      => $student->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('biometric_enrollments', [
            'device_id'      => $this->device->id,
            'device_user_id' => '42',
            'person_type'    => 'student',
            'person_id'      => $student->id,
        ]);
    }

    public function test_enrollment_validates_required_fields(): void
    {
        $this->asAdmin()->post("/biometric/{$this->device->id}/enroll", [])
            ->assertSessionHasErrors(['device_user_id', 'person_type', 'person_id']);
    }

    public function test_destroy_enrollment(): void
    {
        $enrollment = BiometricEnrollment::create([
            'tenant_id'      => $this->tenant->id,
            'device_id'      => $this->device->id,
            'device_user_id' => '99',
            'person_type'    => 'student',
            'person_id'      => 1,
        ]);

        $this->asAdmin()->delete("/biometric/enrollments/{$enrollment->id}")->assertRedirect();
        $this->assertDatabaseMissing('biometric_enrollments', ['id' => $enrollment->id]);
    }

    // ── Recent logs JSON ──────────────────────────────────────────────────────

    public function test_recent_logs_returns_json(): void
    {
        BiometricLog::create([
            'tenant_id'      => $this->tenant->id,
            'device_id'      => $this->device->id,
            'device_user_id' => '1',
            'verified_at'    => now(),
            'verify_type'    => 0,
            'direction'      => 0,
        ]);

        $this->asAdmin()->getJson('/biometric/recent')
            ->assertOk()
            ->assertJsonStructure([['id', 'user_id', 'device', 'time', 'verify_type', 'direction', 'processed']]);
    }
}
