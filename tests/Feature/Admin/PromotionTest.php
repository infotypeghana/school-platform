<?php

namespace Tests\Feature\Admin;

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

    public function test_promotion_index_returns_200(): void
    {
        $this->asAdmin()->get('/students/promotion')
            ->assertOk()
            ->assertViewIs('admin.students.promotion');
    }

    public function test_preview_shows_students(): void
    {
        Student::factory()->count(3)->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/students/promotion/preview', [
            'from_class_id' => $this->fromClass->id,
            'to_class_id'   => $this->toClass->id,
            'action'        => 'promote',
        ])->assertOk()
          ->assertViewHas('students');
    }

    public function test_execute_promotes_students_to_new_class(): void
    {
        $students = Student::factory()->count(4)->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id' => $this->fromClass->id,
            'to_class_id'   => $this->toClass->id,
            'action'        => 'promote',
            'confirmed'     => '1',
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

    public function test_execute_graduates_students(): void
    {
        $students = Student::factory()->count(3)->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->fromClass->id,
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id' => $this->fromClass->id,
            'action'        => 'graduate',
            'confirmed'     => '1',
        ])->assertRedirect('/students')
          ->assertSessionHas('success');

        foreach ($students as $student) {
            $this->assertDatabaseHas('students', [
                'id'     => $student->id,
                'status' => 'graduated',
            ]);
        }
    }

    public function test_promote_does_not_affect_inactive_students(): void
    {
        $active   = Student::factory()->create([
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
            'from_class_id' => $this->fromClass->id,
            'to_class_id'   => $this->toClass->id,
            'action'        => 'promote',
            'confirmed'     => '1',
        ]);

        // Active student promoted
        $this->assertDatabaseHas('students', ['id' => $active->id, 'school_class_id' => $this->toClass->id]);

        // Inactive student NOT moved
        $this->assertDatabaseHas('students', ['id' => $inactive->id, 'school_class_id' => $this->fromClass->id]);
    }

    public function test_execute_requires_confirmation(): void
    {
        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id' => $this->fromClass->id,
            'to_class_id'   => $this->toClass->id,
            'action'        => 'promote',
            // 'confirmed' missing
        ])->assertSessionHasErrors('confirmed');
    }

    public function test_execute_requires_from_class(): void
    {
        $this->asAdmin()->post('/students/promotion/execute', [
            'action'    => 'promote',
            'confirmed' => '1',
        ])->assertSessionHasErrors('from_class_id');
    }

    public function test_empty_class_redirects_with_error(): void
    {
        // No students in fromClass
        $this->asAdmin()->post('/students/promotion/execute', [
            'from_class_id' => $this->fromClass->id,
            'to_class_id'   => $this->toClass->id,
            'action'        => 'promote',
            'confirmed'     => '1',
        ])->assertRedirect()
          ->assertSessionHas('error');
    }

    public function test_guest_redirected_from_promotion(): void
    {
        $this->asGuest()->get('/students/promotion')->assertRedirect();
    }
}
