<?php

namespace Tests\Feature\Api;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * API: GET /api/v1/students and GET /api/v1/students/{id}
 */
class StudentsApiTest extends ApiTestCase
{
    private SchoolClass $class;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 5',
        ]);

        $this->student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'first_name'      => 'Kofi',
            'last_name'       => 'Mensah',
            'status'          => 'active',
        ]);
    }

    public function test_students_list_requires_auth(): void
    {
        $this->getJson('/api/v1/students')->assertUnauthorized();
    }

    public function test_students_list_returns_paginated_results(): void
    {
        $response = $this->apiAs('getJson', '/api/v1/students');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'per_page', 'current_page', 'last_page']]);

        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    public function test_students_list_contains_created_student(): void
    {
        $response = $this->apiAs('getJson', '/api/v1/students');

        $names = collect($response->json('data'))->pluck('full_name');
        $this->assertTrue($names->contains('Kofi Mensah'));
    }

    public function test_students_can_be_filtered_by_class(): void
    {
        // Student in a different class should not appear
        $otherClass = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Basic 6']);
        Student::factory()->create(['tenant_id' => $this->tenant->id, 'school_class_id' => $otherClass->id, 'status' => 'active']);

        $response = $this->apiAs('getJson', "/api/v1/students?class_id={$this->class->id}");

        $response->assertOk();
        foreach ($response->json('data') as $s) {
            $this->assertEquals($this->class->id, $s['class']['id']);
        }
    }

    public function test_students_can_be_searched_by_name(): void
    {
        $response = $this->apiAs('getJson', '/api/v1/students?search=Mensah');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    public function test_tenant_isolation_students_from_other_school_are_hidden(): void
    {
        // Create a student in a completely separate tenant
        $otherTenant = Tenant::create([
            'uuid' => Str::uuid(), 'slug' => 'other-school', 'name' => 'Other School',
            'email' => 'info@other.edu.gh', 'status' => 'active',
        ]);
        $otherClass = SchoolClass::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'Basic 1']);
        $ghost = Student::factory()->create([
            'tenant_id' => $otherTenant->id, 'school_class_id' => $otherClass->id,
            'first_name' => 'Ghost', 'last_name' => 'Student', 'status' => 'active',
        ]);

        $response = $this->apiAs('getJson', '/api/v1/students');
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($ghost->id));
    }

    public function test_student_show_returns_full_profile(): void
    {
        $response = $this->apiAs('getJson', "/api/v1/students/{$this->student->id}");

        $response->assertOk()
            ->assertJsonPath('id', $this->student->id)
            ->assertJsonPath('first_name', 'Kofi')
            ->assertJsonStructure(['attendance_summary', 'fee_summary', 'class']);
    }

    public function test_student_show_from_other_tenant_returns_404(): void
    {
        $otherTenant = Tenant::create([
            'uuid' => Str::uuid(), 'slug' => 'other-school-2', 'name' => 'Other School 2',
            'email' => 'info@other2.edu.gh', 'status' => 'active',
        ]);
        $otherClass = SchoolClass::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'Basic 2']);
        $ghost = Student::factory()->create([
            'tenant_id' => $otherTenant->id, 'school_class_id' => $otherClass->id, 'status' => 'active',
        ]);

        // The HasTenantScope will exclude the student → route model binding → 404
        $this->apiAs('getJson', "/api/v1/students/{$ghost->id}")->assertNotFound();
    }
}
