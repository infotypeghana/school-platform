<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;

/**
 * API: GET/POST /api/v1/attendance
 */
class AttendanceApiTest extends ApiTestCase
{
    private SchoolClass $class;
    private Student $studentA;
    private Student $studentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 4',
        ]);

        $this->studentA = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $this->studentB = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);
    }

    public function test_attendance_index_requires_class_id(): void
    {
        $this->apiAs('getJson', '/api/v1/attendance')
            ->assertStatus(422)
            ->assertJsonValidationErrors('class_id');
    }

    public function test_attendance_index_returns_student_list_with_status(): void
    {
        // Pre-existing record for studentA
        Attendance::create([
            'tenant_id'       => $this->tenant->id,
            'student_id'      => $this->studentA->id,
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
            'date'            => now()->toDateString(),
            'status'          => 'present',
        ]);

        $response = $this->apiAs('getJson', "/api/v1/attendance?class_id={$this->class->id}");

        $response->assertOk()
            ->assertJsonStructure(['date', 'class_id', 'records']);

        $records = collect($response->json('records'));
        $this->assertEquals(2, $records->count());

        $recordA = $records->firstWhere('student_id', $this->studentA->id);
        $this->assertEquals('present', $recordA['status']);

        $recordB = $records->firstWhere('student_id', $this->studentB->id);
        $this->assertNull($recordB['status']); // not recorded yet
    }

    public function test_attendance_store_records_attendance(): void
    {
        $response = $this->apiAs('postJson', '/api/v1/attendance', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
            'date'     => now()->toDateString(),
            'records'  => [
                ['student_id' => $this->studentA->id, 'status' => 'present', 'remark' => ''],
                ['student_id' => $this->studentB->id, 'status' => 'absent',  'remark' => 'Sick'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('count', 2);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->studentA->id,
            'status'     => 'present',
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->studentB->id,
            'status'     => 'absent',
            'remark'     => 'Sick',
        ]);
    }

    public function test_attendance_store_updates_existing_record(): void
    {
        // Create initial record
        Attendance::create([
            'tenant_id'       => $this->tenant->id,
            'student_id'      => $this->studentA->id,
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
            'date'            => now()->toDateString(),
            'status'          => 'present',
        ]);

        // Change to absent
        $this->apiAs('postJson', '/api/v1/attendance', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
            'date'     => now()->toDateString(),
            'records'  => [
                ['student_id' => $this->studentA->id, 'status' => 'absent', 'remark' => 'Late arrival'],
            ],
        ])->assertStatus(201);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->studentA->id,
            'status'     => 'absent',
        ]);

        // Should be only one record (updateOrCreate)
        $this->assertEquals(1, Attendance::where('student_id', $this->studentA->id)->count());
    }

    public function test_attendance_store_rejects_invalid_status(): void
    {
        $this->apiAs('postJson', '/api/v1/attendance', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
            'date'     => now()->toDateString(),
            'records'  => [
                ['student_id' => $this->studentA->id, 'status' => 'unknown'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('records.0.status');
    }

    public function test_attendance_requires_auth(): void
    {
        $this->getJson('/api/v1/attendance?class_id=1')->assertUnauthorized();
        $this->postJson('/api/v1/attendance', [])->assertUnauthorized();
    }
}
