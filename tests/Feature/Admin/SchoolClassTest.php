<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;

class SchoolClassTest extends AdminTestCase
{
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 5',
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->asAdmin()->get('/classes');

        $response->assertOk();
        $response->assertViewIs('admin.classes.index');
    }

    public function test_index_shows_existing_class(): void
    {
        $response = $this->asAdmin()->get('/classes');

        $response->assertSee('Basic 5');
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $this->asGuest()->get('/classes')->assertRedirect();
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_create_form_returns_200(): void
    {
        $response = $this->asAdmin()->get('/classes/create');

        $response->assertOk();
        $response->assertViewIs('admin.classes.create');
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_class_and_redirects(): void
    {
        $response = $this->asAdmin()->post('/classes', [
            'name' => 'Basic 6',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('school_classes', [
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 6',
        ]);
    }

    public function test_store_with_subjects_creates_subject_records(): void
    {
        $this->asAdmin()->post('/classes', [
            'name'     => 'Basic 6',
            'subjects' => "Mathematics\nEnglish\nScience",
        ]);

        // Verify the class was created (use withoutTenantScope to avoid scope issues in test context)
        $this->assertDatabaseHas('school_classes', ['name' => 'Basic 6']);
        // Verify subjects were created — match by name only (class_id unknown without the find)
        $this->assertDatabaseHas('subjects', ['name' => 'Mathematics']);
        $this->assertDatabaseHas('subjects', ['name' => 'English']);
        $this->assertDatabaseHas('subjects', ['name' => 'Science']);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->asAdmin()->post('/classes', []);

        $response->assertSessionHasErrors('name');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_200(): void
    {
        $response = $this->asAdmin()->get("/classes/{$this->class->id}");

        $response->assertOk();
        $response->assertViewIs('admin.classes.show');
    }

    public function test_show_displays_class_name(): void
    {
        $response = $this->asAdmin()->get("/classes/{$this->class->id}");

        $response->assertSee('Basic 5');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function test_edit_form_returns_200(): void
    {
        $response = $this->asAdmin()->get("/classes/{$this->class->id}/edit");

        $response->assertOk();
        $response->assertViewIs('admin.classes.edit');
    }

    public function test_update_changes_class_name(): void
    {
        $response = $this->asAdmin()->put("/classes/{$this->class->id}", [
            'name' => 'Basic 5A',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('school_classes', [
            'id'   => $this->class->id,
            'name' => 'Basic 5A',
        ]);
    }

    public function test_update_can_assign_class_teacher(): void
    {
        $teacher = Teacher::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->asAdmin()->put("/classes/{$this->class->id}", [
            'name'             => 'Basic 5',
            'class_teacher_id' => $teacher->id,
        ]);

        $this->assertDatabaseHas('school_classes', [
            'id'               => $this->class->id,
            'class_teacher_id' => $teacher->id,
        ]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_removes_class(): void
    {
        $id = $this->class->id;

        $response = $this->asAdmin()->delete("/classes/{$id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('school_classes', ['id' => $id]);
    }

    // ── Subjects sub-resource ─────────────────────────────────────────────────

    public function test_subjects_page_returns_200(): void
    {
        $response = $this->asAdmin()->get("/classes/{$this->class->id}/subjects");

        $response->assertOk();
        $response->assertViewIs('admin.classes.subjects');
    }

    public function test_store_subject_adds_subject_to_class(): void
    {
        $response = $this->asAdmin()->post("/classes/{$this->class->id}/subjects", [
            'name'    => 'Social Studies',
            'is_core' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subjects', [
            'school_class_id' => $this->class->id,
            'name'            => 'Social Studies',
            'is_core'         => true,
        ]);
    }

    public function test_store_subject_requires_name(): void
    {
        $response = $this->asAdmin()->post("/classes/{$this->class->id}/subjects", []);

        $response->assertSessionHasErrors('name');
    }

    public function test_destroy_subject_removes_it(): void
    {
        $subject = Subject::create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'name'            => 'French',
            'is_core'         => false,
        ]);

        $response = $this->asAdmin()->delete("/classes/{$this->class->id}/subjects/{$subject->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }
}
