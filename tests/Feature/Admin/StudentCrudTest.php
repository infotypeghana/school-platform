<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Support\Str;

class StudentCrudTest extends AdminTestCase
{
    private SchoolClass $class;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 4',
        ]);

        $this->student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'first_name'      => 'Kofi',
            'last_name'       => 'Mensah',
            'status'          => 'active',
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->asAdmin()->get('/students');

        $response->assertOk();
        $response->assertViewIs('admin.students.index');
    }

    public function test_index_lists_own_tenant_students(): void
    {
        $response = $this->asAdmin()->get('/students');

        $response->assertSee('Kofi');
        $response->assertSee('Mensah');
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $this->asGuest()->get('/students')->assertRedirect();
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_create_form_returns_200(): void
    {
        $response = $this->asAdmin()->get('/students/create');

        $response->assertOk();
        $response->assertViewIs('admin.students.create');
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_student_and_redirects(): void
    {
        $response = $this->asAdmin()->post('/students', [
            'first_name'      => 'Ama',
            'last_name'       => 'Boateng',
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('students', [
            'tenant_id'  => $this->tenant->id,
            'first_name' => 'Ama',
            'last_name'  => 'Boateng',
        ]);
    }

    public function test_store_requires_first_name(): void
    {
        $response = $this->asAdmin()->post('/students', [
            'last_name'       => 'Boateng',
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $response->assertSessionHasErrors('first_name');
    }

    public function test_store_requires_last_name(): void
    {
        $response = $this->asAdmin()->post('/students', [
            'first_name'      => 'Ama',
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $response->assertSessionHasErrors('last_name');
    }

    public function test_store_requires_valid_class(): void
    {
        $response = $this->asAdmin()->post('/students', [
            'first_name'      => 'Ama',
            'last_name'       => 'Boateng',
            'school_class_id' => 99999,
            'status'          => 'active',
        ]);

        $response->assertSessionHasErrors('school_class_id');
    }

    public function test_store_requires_valid_status(): void
    {
        $response = $this->asAdmin()->post('/students', [
            'first_name'      => 'Ama',
            'last_name'       => 'Boateng',
            'school_class_id' => $this->class->id,
            'status'          => 'invalid_status',
        ]);

        $response->assertSessionHasErrors('status');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_200_for_own_student(): void
    {
        $response = $this->asAdmin()->get("/students/{$this->student->id}");

        $response->assertOk();
        $response->assertViewIs('admin.students.show');
    }

    public function test_show_denies_cross_tenant_student(): void
    {
        $otherTenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'other-school',
            'name'   => 'Other',
            'email'  => 'x@other.edu.gh',
            'status' => 'active',
        ]);
        $otherClass = SchoolClass::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignStudent = Student::factory()->create([
            'tenant_id'       => $otherTenant->id,
            'school_class_id' => $otherClass->id,
        ]);

        $response = $this->asAdmin()->get("/students/{$foreignStudent->id}");

        // Global scope hides cross-tenant records → 404
        $this->assertContains($response->status(), [403, 404]);
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function test_edit_form_returns_200(): void
    {
        $response = $this->asAdmin()->get("/students/{$this->student->id}/edit");

        $response->assertOk();
        $response->assertViewIs('admin.students.edit');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_changes_student_name(): void
    {
        $response = $this->asAdmin()->put("/students/{$this->student->id}", [
            'first_name'      => 'Kwesi',
            'last_name'       => 'Mensah',
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('students', [
            'id'         => $this->student->id,
            'first_name' => 'Kwesi',
        ]);
    }

    public function test_update_requires_first_name(): void
    {
        $response = $this->asAdmin()->put("/students/{$this->student->id}", [
            'last_name'       => 'Mensah',
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $response->assertSessionHasErrors('first_name');
    }

    // ── Attendance summary on show ────────────────────────────────────────────

    public function test_show_passes_attendance_summary_to_view(): void
    {
        \App\Models\Attendance::create([
            'tenant_id'       => $this->tenant->id,
            'student_id'      => $this->student->id,
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
            'date'            => now()->subDays(1)->toDateString(),
            'status'          => 'present',
        ]);
        \App\Models\Attendance::create([
            'tenant_id'       => $this->tenant->id,
            'student_id'      => $this->student->id,
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
            'date'            => now()->subDays(2)->toDateString(),
            'status'          => 'absent',
        ]);

        $response = $this->asAdmin()->get("/students/{$this->student->id}");

        $response->assertViewHas('attendanceSummary');
        $summary = $response->viewData('attendanceSummary');
        $this->assertSame(2,  $summary['total']);
        $this->assertSame(1,  $summary['present']);
        $this->assertSame(1,  $summary['absent']);
        $this->assertEquals(50, $summary['rate']);
    }

    public function test_show_attendance_summary_is_zero_when_no_records(): void
    {
        $response = $this->asAdmin()->get("/students/{$this->student->id}");

        $summary = $response->viewData('attendanceSummary');
        $this->assertSame(0,    $summary['total']);
        $this->assertNull($summary['rate']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_removes_student(): void
    {
        $id = $this->student->id;

        $response = $this->asAdmin()->delete("/students/{$id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        // Student uses SoftDeletes — row exists with deleted_at set
        $this->assertSoftDeleted('students', ['id' => $id]);
    }
}
