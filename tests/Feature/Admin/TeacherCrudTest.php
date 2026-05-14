<?php

namespace Tests\Feature\Admin;

use App\Models\Teacher;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TeacherCrudTest extends AdminTestCase
{
    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = Teacher::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'first_name' => 'Yaw',
            'last_name'  => 'Darko',
            'status'     => 'active',
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->asAdmin()->get('/teachers');

        $response->assertOk();
        $response->assertViewIs('admin.teachers.index');
    }

    public function test_index_lists_own_tenant_teachers(): void
    {
        $response = $this->asAdmin()->get('/teachers');

        $response->assertSee('Yaw');
        $response->assertSee('Darko');
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $this->asGuest()->get('/teachers')->assertRedirect();
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_create_form_returns_200(): void
    {
        $response = $this->asAdmin()->get('/teachers/create');

        $response->assertOk();
        $response->assertViewIs('admin.teachers.create');
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_teacher_and_redirects(): void
    {
        $response = $this->asAdmin()->post('/teachers', [
            'first_name' => 'Abena',
            'last_name'  => 'Asante',
            'status'     => 'active',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', [
            'tenant_id'  => $this->tenant->id,
            'first_name' => 'Abena',
            'last_name'  => 'Asante',
        ]);
    }

    public function test_store_requires_first_name(): void
    {
        $response = $this->asAdmin()->post('/teachers', [
            'last_name' => 'Asante',
            'status'    => 'active',
        ]);

        $response->assertSessionHasErrors('first_name');
    }

    public function test_store_requires_last_name(): void
    {
        $response = $this->asAdmin()->post('/teachers', [
            'first_name' => 'Abena',
            'status'     => 'active',
        ]);

        $response->assertSessionHasErrors('last_name');
    }

    public function test_store_requires_valid_status(): void
    {
        $response = $this->asAdmin()->post('/teachers', [
            'first_name' => 'Abena',
            'last_name'  => 'Asante',
            'status'     => 'retired', // not in allowed enum
        ]);

        $response->assertSessionHasErrors('status');
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function test_edit_form_returns_200(): void
    {
        $response = $this->asAdmin()->get("/teachers/{$this->teacher->id}/edit");

        $response->assertOk();
        $response->assertViewIs('admin.teachers.edit');
    }

    public function test_edit_denies_cross_tenant_teacher(): void
    {
        $otherTenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'other-school-t',
            'name'   => 'Other',
            'email'  => 'x@othert.edu.gh',
            'status' => 'active',
        ]);
        $foreignTeacher = Teacher::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->asAdmin()->get("/teachers/{$foreignTeacher->id}/edit");

        $this->assertContains($response->status(), [403, 404]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_changes_teacher_name(): void
    {
        $response = $this->asAdmin()->put("/teachers/{$this->teacher->id}", [
            'first_name' => 'Kwabena',
            'last_name'  => 'Darko',
            'status'     => 'active',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', [
            'id'         => $this->teacher->id,
            'first_name' => 'Kwabena',
        ]);
    }

    public function test_update_requires_first_name(): void
    {
        $response = $this->asAdmin()->put("/teachers/{$this->teacher->id}", [
            'last_name' => 'Darko',
            'status'    => 'active',
        ]);

        $response->assertSessionHasErrors('first_name');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_removes_teacher(): void
    {
        $id = $this->teacher->id;

        $response = $this->asAdmin()->delete("/teachers/{$id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        // Teacher uses SoftDeletes — row exists with deleted_at set
        $this->assertSoftDeleted('teachers', ['id' => $id]);
    }

    public function test_destroy_denies_cross_tenant_teacher(): void
    {
        $otherTenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'other-school-td',
            'name'   => 'Other',
            'email'  => 'x@othertd.edu.gh',
            'status' => 'active',
        ]);
        $foreignTeacher = Teacher::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->asAdmin()->delete("/teachers/{$foreignTeacher->id}");

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('teachers', ['id' => $foreignTeacher->id]);
    }
}
