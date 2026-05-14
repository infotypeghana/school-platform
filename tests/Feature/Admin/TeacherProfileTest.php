<?php

namespace Tests\Feature\Admin;

use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

class TeacherProfileTest extends AdminTestCase
{
    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = Teacher::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'first_name' => 'Abena',
            'last_name'  => 'Owusu',
            'status'     => 'active',
        ]);
    }

    // ── Basic access ──────────────────────────────────────────────────────────

    public function test_show_returns_200_for_own_tenant_teacher(): void
    {
        $response = $this->asAdmin()->get("/teachers/{$this->teacher->id}");

        $response->assertOk();
        $response->assertViewIs('admin.teachers.show');
    }

    public function test_show_passes_teacher_to_view(): void
    {
        $response = $this->asAdmin()->get("/teachers/{$this->teacher->id}");

        $response->assertViewHas('teacher');
        $viewTeacher = $response->viewData('teacher');
        $this->assertSame($this->teacher->id, $viewTeacher->id);
    }

    public function test_show_displays_teacher_name(): void
    {
        $response = $this->asAdmin()->get("/teachers/{$this->teacher->id}");

        $response->assertSee('Abena');
        $response->assertSee('Owusu');
    }

    // ── Relations loaded ──────────────────────────────────────────────────────

    public function test_show_loads_school_classes_relation(): void
    {
        $response = $this->asAdmin()->get("/teachers/{$this->teacher->id}");

        $viewTeacher = $response->viewData('teacher');
        $this->assertTrue($viewTeacher->relationLoaded('schoolClasses'));
    }

    public function test_show_loads_subjects_relation(): void
    {
        $response = $this->asAdmin()->get("/teachers/{$this->teacher->id}");

        $viewTeacher = $response->viewData('teacher');
        $this->assertTrue($viewTeacher->relationLoaded('subjects'));
    }

    // ── Cross-tenant protection ───────────────────────────────────────────────

    public function test_show_denies_access_to_other_tenants_teacher(): void
    {
        // Create a second tenant and a teacher belonging to it
        $otherTenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'other-school',
            'name'   => 'Other School',
            'email'  => 'admin@other.edu.gh',
            'status' => 'active',
        ]);

        $foreignTeacher = Teacher::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Our admin (from $this->tenant) tries to view the other school's teacher
        $response = $this->asAdmin()->get("/teachers/{$foreignTeacher->id}");

        // Should be 403 (policy denies cross-tenant) or 404 (global scope hides it)
        $this->assertContains($response->status(), [403, 404]);
    }

    // ── Index links to show ───────────────────────────────────────────────────

    public function test_teacher_index_contains_view_link(): void
    {
        $response = $this->asAdmin()->get('/teachers');

        $response->assertOk();
        $response->assertSee("/teachers/{$this->teacher->id}");
    }

    // ── Guest is blocked ──────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_show(): void
    {
        $response = $this->asGuest()->get("/teachers/{$this->teacher->id}");

        $response->assertRedirect();
    }
}
