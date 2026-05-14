<?php

namespace Tests\Feature\Admin;

use App\Models\Admission;
use App\Models\SchoolClass;
use App\Models\Student;

class AdmissionEnrollTest extends AdminTestCase
{
    private Admission $admission;
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 3',
        ]);

        $this->admission = Admission::create([
            'tenant_id'          => $this->tenant->id,
            'first_name'         => 'Kwame',
            'last_name'          => 'Asante',
            'date_of_birth'      => '2010-06-15',
            'gender'             => 'male',
            'guardian_name'      => 'Yaw Asante',
            'guardian_phone'     => '0244123456',
            'guardian_email'     => 'yaw@example.com',
            'address'            => '12 Ring Road, Accra',
            'class_applying_for' => 'Basic 3',
            'status'             => 'accepted',
            'submitted_at'       => now()->subDays(3),
        ]);
    }

    // ── Enrol Form ────────────────────────────────────────────────────────────

    public function test_enroll_form_returns_200_for_accepted_admission(): void
    {
        $response = $this->asAdmin()->get("/admissions/{$this->admission->id}/enroll");

        $response->assertOk();
        $response->assertViewIs('admin.admissions.enroll');
        $response->assertViewHas('admission', $this->admission->fresh());
    }

    public function test_enroll_form_redirects_back_for_pending_admission(): void
    {
        $pending = Admission::create([
            'tenant_id'          => $this->tenant->id,
            'first_name'         => 'Ama',
            'last_name'          => 'Boadu',
            'gender'             => 'female',
            'guardian_name'      => 'Efua Boadu',
            'guardian_phone'     => '0244000001',
            'class_applying_for' => 'Basic 1',
            'status'             => 'pending',
            'submitted_at'       => now(),
        ]);

        $response = $this->asAdmin()->get("/admissions/{$pending->id}/enroll");

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_enroll_form_redirects_to_student_if_already_enrolled(): void
    {
        // First create the student manually and link to admission
        $student = Student::create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'first_name'      => 'Kwame',
            'last_name'       => 'Asante',
            'gender'          => 'male',
            'status'          => 'active',
        ]);

        $this->admission->update([
            'student_id'  => $student->id,
            'status'      => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $response = $this->asAdmin()->get("/admissions/{$this->admission->id}/enroll");

        $response->assertRedirectContains("/students/{$student->id}");
        $response->assertSessionHas('info');
    }

    // ── Enrol POST: happy path ────────────────────────────────────────────────

    public function test_enroll_creates_student_record(): void
    {
        $payload = [
            'first_name'      => 'Kwame',
            'last_name'       => 'Asante',
            'school_class_id' => $this->class->id,
            'date_of_birth'   => '2010-06-15',
            'gender'          => 'male',
            'guardian_name'   => 'Yaw Asante',
            'guardian_phone'  => '0244123456',
            'guardian_email'  => 'yaw@example.com',
            'address'         => '12 Ring Road, Accra',
            'admission_date'  => now()->toDateString(),
        ];

        $response = $this->asAdmin()->post("/admissions/{$this->admission->id}/enroll", $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Student row was created
        $this->assertDatabaseHas('students', [
            'tenant_id'       => $this->tenant->id,
            'first_name'      => 'Kwame',
            'last_name'       => 'Asante',
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);
    }

    public function test_enroll_auto_generates_admission_number(): void
    {
        $payload = [
            'first_name'      => 'Kwame',
            'last_name'       => 'Asante',
            'school_class_id' => $this->class->id,
            'date_of_birth'   => '2010-06-15',
            'gender'          => 'male',
            'guardian_name'   => 'Yaw Asante',
            'guardian_phone'  => '0244123456',
            'admission_date'  => now()->toDateString(),
        ];

        $this->asAdmin()->post("/admissions/{$this->admission->id}/enroll", $payload);

        $student = Student::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('first_name', 'Kwame')
            ->first();

        $this->assertNotNull($student);
        $this->assertMatchesRegularExpression('/^ADM-\d{4}-\d{4}$/', $student->admission_number);
    }

    public function test_enroll_links_admission_to_student(): void
    {
        $payload = [
            'first_name'      => 'Kwame',
            'last_name'       => 'Asante',
            'school_class_id' => $this->class->id,
            'date_of_birth'   => '2010-06-15',
            'gender'          => 'male',
            'guardian_name'   => 'Yaw Asante',
            'guardian_phone'  => '0244123456',
            'admission_date'  => now()->toDateString(),
        ];

        $this->asAdmin()->post("/admissions/{$this->admission->id}/enroll", $payload);

        $fresh = $this->admission->fresh();

        $this->assertSame('enrolled', $fresh->status);
        $this->assertNotNull($fresh->student_id);
        $this->assertNotNull($fresh->enrolled_at);
    }

    public function test_enroll_redirects_to_student_show_page(): void
    {
        $payload = [
            'first_name'      => 'Kwame',
            'last_name'       => 'Asante',
            'school_class_id' => $this->class->id,
            'gender'          => 'male',
            'guardian_name'   => 'Yaw Asante',
            'guardian_phone'  => '0244123456',
            'admission_date'  => now()->toDateString(),
        ];

        $response = $this->asAdmin()->post("/admissions/{$this->admission->id}/enroll", $payload);

        $student = Student::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('first_name', 'Kwame')
            ->first();

        $response->assertRedirectContains("/students/{$student->id}");
    }

    // ── Enrol POST: guard cases ───────────────────────────────────────────────

    public function test_enroll_rejects_non_accepted_admission(): void
    {
        $this->admission->update(['status' => 'pending']);

        $payload = [
            'first_name'      => 'Kwame',
            'last_name'       => 'Asante',
            'school_class_id' => $this->class->id,
            'gender'          => 'male',
            'guardian_name'   => 'Yaw Asante',
            'guardian_phone'  => '0244123456',
            'admission_date'  => now()->toDateString(),
        ];

        $response = $this->asAdmin()->post("/admissions/{$this->admission->id}/enroll", $payload);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // No student created
        $this->assertDatabaseMissing('students', [
            'tenant_id'  => $this->tenant->id,
            'first_name' => 'Kwame',
        ]);
    }

    public function test_enroll_is_idempotent_when_already_enrolled(): void
    {
        // Create student and link admission
        $student = Student::create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'first_name'      => 'Kwame',
            'last_name'       => 'Asante',
            'gender'          => 'male',
            'status'          => 'active',
        ]);

        $this->admission->update([
            'student_id'  => $student->id,
            'status'      => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $payload = [
            'first_name'      => 'Kwame',
            'last_name'       => 'Asante',
            'school_class_id' => $this->class->id,
            'gender'          => 'male',
            'guardian_name'   => 'Yaw Asante',
            'guardian_phone'  => '0244123456',
            'admission_date'  => now()->toDateString(),
        ];

        $response = $this->asAdmin()->post("/admissions/{$this->admission->id}/enroll", $payload);

        $response->assertRedirectContains("/students/{$student->id}");
        $response->assertSessionHas('info');

        // No duplicate student created
        $count = Student::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('first_name', 'Kwame')
            ->count();

        $this->assertSame(1, $count);
    }

    // ── Enrol POST: validation errors ─────────────────────────────────────────

    public function test_enroll_requires_first_name(): void
    {
        $payload = [
            'last_name'       => 'Asante',
            'school_class_id' => $this->class->id,
            'gender'          => 'male',
        ];

        $response = $this->asAdmin()->post("/admissions/{$this->admission->id}/enroll", $payload);

        $response->assertSessionHasErrors('first_name');
    }

    public function test_enroll_requires_last_name(): void
    {
        $payload = [
            'first_name'      => 'Kwame',
            'school_class_id' => $this->class->id,
            'gender'          => 'male',
        ];

        $response = $this->asAdmin()->post("/admissions/{$this->admission->id}/enroll", $payload);

        $response->assertSessionHasErrors('last_name');
    }

    public function test_enroll_requires_valid_school_class_id(): void
    {
        $payload = [
            'first_name'      => 'Kwame',
            'last_name'       => 'Asante',
            'school_class_id' => 99999, // non-existent
            'gender'          => 'male',
        ];

        $response = $this->asAdmin()->post("/admissions/{$this->admission->id}/enroll", $payload);

        $response->assertSessionHasErrors('school_class_id');
    }
}
