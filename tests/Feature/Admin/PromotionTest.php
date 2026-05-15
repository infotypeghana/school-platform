<?php

namespace Tests\Feature\Admin;

use App\Models\Promotion;
use App\Models\SchoolClass;
use App\Models\Student;

class PromotionTest extends AdminTestCase
{
    private SchoolClass $fromClass;
    private SchoolClass $toClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fromClass = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 4',
        ]);

        $this->toClass = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 5',
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_promotion_index_returns_200(): void
    {
        $this->asAdmin()->get('/students/promotion')
            ->assertOk()
            ->assertViewIs('admin.students.promotion');
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    public function test_preview_shows_students(): void
    {
        Student::factory()->count(3)->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/students/promotion/preview', [
            'from_class_id'    => $this->fromClass->id,
            'to_class_id'      => $this->toClass->id,
            'default_action'   => 'promoted',
            'academic_year_id' => $this->year->id,
        ])->assertOk()
          ->assertViewHas('students')
          ->assertViewHas('fromClass')
          ->assertViewHas('academicYear');
    }

    public function test_preview_requires_academic_year(): void
    {
        $this->asAdmin()->post('/students/promotion/preview', [
            'from_class_id'  => $this->fromClass->id,
            'default_action' => 'promoted',
            // academic_year_id missing
        ])->assertSessionHasErrors('academic_year_id');
    }

    public function test_preview_requires_valid_default_action(): void
    {
        $this->asAdmin()->post('/students/promotion/preview', [
            'from_class_id'    => $this->fromClass->id,
            'default_action'   => 'invalid_action',
            'academic_year_id' => $this->year->id,
        ])->assertSessionHasErrors('default_action');
    }

    // ── Execute — promote ─────────────────────────────────────────────────────

    public function test_execute_promotes_students_to_new_class(): void
    {
        $students = Student::factory()->count(4)->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        $studentPayload = [];
        foreach ($students as $s) {
            $studentPayload[$s->id] = [
                'action'      => 'promoted',
                'to_class_id' => $this->toClass->id,
            ];
        }

        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id'    => $this->fromClass->id,
            'academic_year_id' => $this->year->id,
            'students'         => $studentPayload,
        ])->assertRedirect('/students')
          ->assertSessionHas('success');

        foreach ($students as $student) {
            $this->assertDatabaseHas('students', [
                'id'              => $student->id,
                'school_class_id' => $this->toClass->id,
                'status'          => 'active',
            ]);
        }
    }

    public function test_execute_promotes_writes_promotion_records(): void
    {
        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id'    => $this->fromClass->id,
            'academic_year_id' => $this->year->id,
            'students'         => [
                $student->id => ['action' => 'promoted', 'to_class_id' => $this->toClass->id],
            ],
        ]);

        $this->assertDatabaseHas('promotions', [
            'student_id'       => $student->id,
            'academic_year_id' => $this->year->id,
            'from_class_id'    => $this->fromClass->id,
            'to_class_id'      => $this->toClass->id,
            'action'           => 'promoted',
        ]);
    }

    // ── Execute — graduate ────────────────────────────────────────────────────

    public function test_execute_graduates_students(): void
    {
        $students = Student::factory()->count(3)->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        $studentPayload = [];
        foreach ($students as $s) {
            $studentPayload[$s->id] = ['action' => 'graduated'];
        }

        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id'    => $this->fromClass->id,
            'academic_year_id' => $this->year->id,
            'students'         => $studentPayload,
        ])->assertRedirect('/students')
          ->assertSessionHas('success');

        foreach ($students as $student) {
            $this->assertDatabaseHas('students', [
                'id'     => $student->id,
                'status' => 'graduated',
            ]);
            $this->assertDatabaseHas('promotions', [
                'student_id' => $student->id,
                'action'     => 'graduated',
                'to_class_id'=> null,
            ]);
        }
    }

    // ── Execute — hold back ───────────────────────────────────────────────────

    public function test_execute_held_back_keeps_student_in_same_class(): void
    {
        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id'    => $this->fromClass->id,
            'academic_year_id' => $this->year->id,
            'students'         => [
                $student->id => ['action' => 'held_back'],
            ],
        ])->assertRedirect('/students');

        // Student stays in the same class
        $this->assertDatabaseHas('students', [
            'id'              => $student->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        // But a promotion record is written
        $this->assertDatabaseHas('promotions', [
            'student_id'  => $student->id,
            'action'      => 'held_back',
            'from_class_id' => $this->fromClass->id,
            'to_class_id'   => $this->fromClass->id,
        ]);
    }

    // ── Execute — skip ────────────────────────────────────────────────────────

    public function test_execute_skipped_students_produce_no_record(): void
    {
        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id'    => $this->fromClass->id,
            'academic_year_id' => $this->year->id,
            'students'         => [
                $student->id => ['action' => 'skip'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('promotions', ['student_id' => $student->id]);
        // Student record unchanged
        $this->assertDatabaseHas('students', [
            'id'              => $student->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);
    }

    // ── Inactive student guard ────────────────────────────────────────────────

    public function test_execute_does_not_affect_inactive_students(): void
    {
        $active = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);
        $inactive = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'withdrawn',
        ]);

        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id'    => $this->fromClass->id,
            'academic_year_id' => $this->year->id,
            'students'         => [
                $active->id   => ['action' => 'promoted', 'to_class_id' => $this->toClass->id],
                $inactive->id => ['action' => 'promoted', 'to_class_id' => $this->toClass->id],
            ],
        ]);

        // Active student promoted
        $this->assertDatabaseHas('students', [
            'id'              => $active->id,
            'school_class_id' => $this->toClass->id,
        ]);
        // Inactive student NOT touched
        $this->assertDatabaseHas('students', [
            'id'              => $inactive->id,
            'school_class_id' => $this->fromClass->id,
        ]);
        // No promotion record for the inactive student
        $this->assertDatabaseMissing('promotions', ['student_id' => $inactive->id]);
    }

    // ── Double-promotion guard ────────────────────────────────────────────────

    public function test_execute_skips_student_already_promoted_this_year(): void
    {
        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        // Simulate a prior promotion record for the same year
        Promotion::create([
            'tenant_id'        => $this->tenant->id,
            'student_id'       => $student->id,
            'academic_year_id' => $this->year->id,
            'from_class_id'    => $this->fromClass->id,
            'to_class_id'      => $this->toClass->id,
            'action'           => 'promoted',
            'promoted_by'      => null,
        ]);

        // Try to promote again in the same year
        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id'    => $this->fromClass->id,
            'academic_year_id' => $this->year->id,
            'students'         => [
                $student->id => ['action' => 'promoted', 'to_class_id' => $this->toClass->id],
            ],
        ])->assertRedirect();

        // Only one promotion record should exist
        $this->assertDatabaseCount('promotions', 1);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_execute_requires_from_class(): void
    {
        $this->asAdmin()->post('/students/promotion/execute', [
            'academic_year_id' => $this->year->id,
            'students'         => ['1' => ['action' => 'promoted']],
        ])->assertSessionHasErrors('from_class_id');
    }

    public function test_execute_requires_academic_year(): void
    {
        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id' => $this->fromClass->id,
            'students'      => ['1' => ['action' => 'promoted']],
        ])->assertSessionHasErrors('academic_year_id');
    }

    public function test_execute_requires_students_array(): void
    {
        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id'    => $this->fromClass->id,
            'academic_year_id' => $this->year->id,
            // students missing
        ])->assertSessionHasErrors('students');
    }

    public function test_execute_rejects_invalid_action(): void
    {
        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id'    => $this->fromClass->id,
            'academic_year_id' => $this->year->id,
            'students'         => [
                $student->id => ['action' => 'not_valid'],
            ],
        ])->assertSessionHasErrors();
    }

    // ── History ───────────────────────────────────────────────────────────────

    public function test_history_page_returns_200(): void
    {
        $this->asAdmin()->get('/students/promotion/history')
            ->assertOk()
            ->assertViewIs('admin.students.promotion-history');
    }

    public function test_history_shows_promotion_records(): void
    {
        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        Promotion::create([
            'tenant_id'        => $this->tenant->id,
            'student_id'       => $student->id,
            'academic_year_id' => $this->year->id,
            'from_class_id'    => $this->fromClass->id,
            'to_class_id'      => $this->toClass->id,
            'action'           => 'promoted',
            'promoted_by'      => null,
        ]);

        $this->asAdmin()->get('/students/promotion/history')
            ->assertOk()
            ->assertViewHas('promotions');
    }

    // ── Auth guard ────────────────────────────────────────────────────────────

    public function test_guest_redirected_from_promotion(): void
    {
        $this->asGuest()->get('/students/promotion')->assertRedirect();
    }

    public function test_guest_redirected_from_history(): void
    {
        $this->asGuest()->get('/students/promotion/history')->assertRedirect();
    }
}
