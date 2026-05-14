<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timetable;

class TimetableTest extends AdminTestCase
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
        $response = $this->asAdmin()->get('/timetables');

        $response->assertOk();
        $response->assertViewIs('admin.timetables.index');
    }

    public function test_index_with_class_shows_grid(): void
    {
        $response = $this->asAdmin()->get('/timetables?class_id=' . $this->class->id);

        $response->assertOk();
        $response->assertViewHas('class');
    }

    public function test_guest_redirected_from_timetable(): void
    {
        $this->asGuest()->get('/timetables')->assertRedirect();
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_create_form_returns_200(): void
    {
        $response = $this->asAdmin()->get('/timetables/create?class_id=' . $this->class->id);

        $response->assertOk();
        $response->assertViewIs('admin.timetables.form');
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_timetable_entry(): void
    {
        $response = $this->asAdmin()->post('/timetables', [
            'school_class_id' => $this->class->id,
            'day_of_week'     => 1,
            'period_number'   => 1,
            'start_time'      => '07:30',
            'end_time'        => '08:15',
            'label'           => 'Assembly',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('timetables', [
            'school_class_id' => $this->class->id,
            'day_of_week'     => 1,
            'period_number'   => 1,
            'label'           => 'Assembly',
        ]);
    }

    public function test_store_with_subject_and_teacher(): void
    {
        $subject = Subject::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'name'            => 'Mathematics',
        ]);

        $teacher = Teacher::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->asAdmin()->post('/timetables', [
            'school_class_id' => $this->class->id,
            'day_of_week'     => 2,
            'period_number'   => 2,
            'start_time'      => '08:15',
            'end_time'        => '09:00',
            'subject_id'      => $subject->id,
            'teacher_id'      => $teacher->id,
        ]);

        $this->assertDatabaseHas('timetables', [
            'school_class_id' => $this->class->id,
            'subject_id'      => $subject->id,
            'teacher_id'      => $teacher->id,
        ]);
    }

    public function test_store_upserts_existing_slot(): void
    {
        // Create initial entry
        $timetable = Timetable::create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'day_of_week'     => 1,
            'period_number'   => 3,
            'start_time'      => '09:00',
            'end_time'        => '09:45',
            'label'           => 'Old Label',
        ]);

        // Store same slot with new label
        $this->asAdmin()->post('/timetables', [
            'school_class_id' => $this->class->id,
            'day_of_week'     => 1,
            'period_number'   => 3,
            'start_time'      => '09:00',
            'end_time'        => '09:45',
            'label'           => 'New Label',
        ]);

        // Should not have created a duplicate
        $this->assertSame(
            1,
            Timetable::where('school_class_id', $this->class->id)
                ->where('day_of_week', 1)
                ->where('period_number', 3)
                ->count()
        );

        $this->assertDatabaseHas('timetables', ['label' => 'New Label']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->asAdmin()->post('/timetables', []);

        $response->assertSessionHasErrors(['school_class_id', 'day_of_week', 'period_number', 'start_time', 'end_time']);
    }

    public function test_store_rejects_end_before_start(): void
    {
        $response = $this->asAdmin()->post('/timetables', [
            'school_class_id' => $this->class->id,
            'day_of_week'     => 1,
            'period_number'   => 1,
            'start_time'      => '09:00',
            'end_time'        => '08:00',
        ]);

        $response->assertSessionHasErrors('end_time');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function test_edit_returns_200(): void
    {
        $timetable = Timetable::create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'day_of_week'     => 3,
            'period_number'   => 1,
            'start_time'      => '07:30',
            'end_time'        => '08:15',
        ]);

        $response = $this->asAdmin()->get("/timetables/{$timetable->id}/edit");

        $response->assertOk();
        $response->assertViewIs('admin.timetables.form');
    }

    public function test_update_changes_label(): void
    {
        $timetable = Timetable::create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'day_of_week'     => 4,
            'period_number'   => 2,
            'start_time'      => '08:15',
            'end_time'        => '09:00',
            'label'           => 'Break',
        ]);

        $this->asAdmin()->put("/timetables/{$timetable->id}", [
            'school_class_id' => $this->class->id,
            'day_of_week'     => 4,
            'period_number'   => 2,
            'start_time'      => '08:15',
            'end_time'        => '09:00',
            'label'           => 'Long Break',
        ]);

        $this->assertDatabaseHas('timetables', [
            'id'    => $timetable->id,
            'label' => 'Long Break',
        ]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_removes_entry(): void
    {
        $timetable = Timetable::create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'day_of_week'     => 5,
            'period_number'   => 1,
            'start_time'      => '07:30',
            'end_time'        => '08:15',
        ]);

        $this->asAdmin()->delete("/timetables/{$timetable->id}");

        $this->assertDatabaseMissing('timetables', ['id' => $timetable->id]);
    }
}
