<?php

namespace Tests\Unit;

use App\Models\Admission;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\AdmissionPolicy;
use App\Policies\FeePolicy;
use App\Policies\StudentPolicy;
use App\Policies\TeacherPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;   // school_admin for tenant A
    private User $adminB;   // school_admin for tenant B
    private User $superAdmin;
    private AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        // fees.term_id is NOT NULL — need a real term in the DB
        $year = AcademicYear::create(['year_label' => '2024/2025', 'is_current' => true]);
        $this->term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'term_number'      => 1,
            'term_name'        => 'First Term',
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'is_current'       => true,
        ]);

        $this->tenantA = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'school-a',
            'name'   => 'School A',
            'email'  => 'admin@schoola.edu.gh',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'school-b',
            'name'   => 'School B',
            'email'  => 'admin@schoolb.edu.gh',
            'status' => 'active',
        ]);

        $this->adminA = User::factory()->create([
            'role'      => 'school_admin',
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->adminB = User::factory()->create([
            'role'      => 'school_admin',
            'tenant_id' => $this->tenantB->id,
        ]);

        $this->superAdmin = User::factory()->create([
            'role'      => 'super_admin',
            'tenant_id' => null,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // StudentPolicy
    // ══════════════════════════════════════════════════════════════════════════

    public function test_student_policy_view_any_allows_school_admin(): void
    {
        $policy = new StudentPolicy();
        $this->assertTrue($policy->viewAny($this->adminA));
    }

    public function test_student_policy_view_allows_own_tenant(): void
    {
        $policy   = new StudentPolicy();
        $student  = $this->makeStudent($this->tenantA);

        $this->assertTrue($policy->view($this->adminA, $student));
    }

    public function test_student_policy_view_denies_cross_tenant(): void
    {
        $policy  = new StudentPolicy();
        $student = $this->makeStudent($this->tenantB); // belongs to B

        $this->assertFalse($policy->view($this->adminA, $student)); // admin is A
    }

    public function test_student_policy_update_allows_own_tenant(): void
    {
        $policy  = new StudentPolicy();
        $student = $this->makeStudent($this->tenantA);

        $this->assertTrue($policy->update($this->adminA, $student));
    }

    public function test_student_policy_update_denies_cross_tenant(): void
    {
        $policy  = new StudentPolicy();
        $student = $this->makeStudent($this->tenantB);

        $this->assertFalse($policy->update($this->adminA, $student));
    }

    public function test_student_policy_delete_allows_own_tenant(): void
    {
        $policy  = new StudentPolicy();
        $student = $this->makeStudent($this->tenantA);

        $this->assertTrue($policy->delete($this->adminA, $student));
    }

    public function test_student_policy_delete_denies_cross_tenant(): void
    {
        $policy  = new StudentPolicy();
        $student = $this->makeStudent($this->tenantB);

        $this->assertFalse($policy->delete($this->adminA, $student));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TeacherPolicy
    // ══════════════════════════════════════════════════════════════════════════

    public function test_teacher_policy_view_allows_own_tenant(): void
    {
        $policy  = new TeacherPolicy();
        $teacher = Teacher::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->assertTrue($policy->view($this->adminA, $teacher));
    }

    public function test_teacher_policy_view_denies_cross_tenant(): void
    {
        $policy  = new TeacherPolicy();
        $teacher = Teacher::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->assertFalse($policy->view($this->adminA, $teacher));
    }

    public function test_teacher_policy_update_allows_own_tenant(): void
    {
        $policy  = new TeacherPolicy();
        $teacher = Teacher::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->assertTrue($policy->update($this->adminA, $teacher));
    }

    public function test_teacher_policy_update_denies_cross_tenant(): void
    {
        $policy  = new TeacherPolicy();
        $teacher = Teacher::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->assertFalse($policy->update($this->adminA, $teacher));
    }

    public function test_teacher_policy_delete_allows_own_tenant(): void
    {
        $policy  = new TeacherPolicy();
        $teacher = Teacher::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->assertTrue($policy->delete($this->adminA, $teacher));
    }

    public function test_teacher_policy_delete_denies_cross_tenant(): void
    {
        $policy  = new TeacherPolicy();
        $teacher = Teacher::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->assertFalse($policy->delete($this->adminA, $teacher));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FeePolicy
    // ══════════════════════════════════════════════════════════════════════════

    public function test_fee_policy_update_allows_own_tenant(): void
    {
        $policy = new FeePolicy();
        $fee    = $this->makeFee($this->tenantA);

        $this->assertTrue($policy->update($this->adminA, $fee));
    }

    public function test_fee_policy_update_denies_cross_tenant(): void
    {
        $policy = new FeePolicy();
        $fee    = $this->makeFee($this->tenantB);

        $this->assertFalse($policy->update($this->adminA, $fee));
    }

    public function test_fee_policy_delete_allows_own_tenant(): void
    {
        $policy = new FeePolicy();
        $fee    = $this->makeFee($this->tenantA);

        $this->assertTrue($policy->delete($this->adminA, $fee));
    }

    public function test_fee_policy_delete_denies_cross_tenant(): void
    {
        $policy = new FeePolicy();
        $fee    = $this->makeFee($this->tenantB);

        $this->assertFalse($policy->delete($this->adminA, $fee));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // AdmissionPolicy
    // ══════════════════════════════════════════════════════════════════════════

    public function test_admission_policy_view_allows_own_tenant(): void
    {
        $policy    = new AdmissionPolicy();
        $admission = $this->makeAdmission($this->tenantA);

        $this->assertTrue($policy->view($this->adminA, $admission));
    }

    public function test_admission_policy_view_denies_cross_tenant(): void
    {
        $policy    = new AdmissionPolicy();
        $admission = $this->makeAdmission($this->tenantB);

        $this->assertFalse($policy->view($this->adminA, $admission));
    }

    public function test_admission_policy_update_allows_own_tenant(): void
    {
        $policy    = new AdmissionPolicy();
        $admission = $this->makeAdmission($this->tenantA);

        $this->assertTrue($policy->update($this->adminA, $admission));
    }

    public function test_admission_policy_update_denies_cross_tenant(): void
    {
        $policy    = new AdmissionPolicy();
        $admission = $this->makeAdmission($this->tenantB);

        $this->assertFalse($policy->update($this->adminA, $admission));
    }

    public function test_admission_policy_delete_allows_own_tenant(): void
    {
        $policy    = new AdmissionPolicy();
        $admission = $this->makeAdmission($this->tenantA);

        $this->assertTrue($policy->delete($this->adminA, $admission));
    }

    public function test_admission_policy_delete_denies_cross_tenant(): void
    {
        $policy    = new AdmissionPolicy();
        $admission = $this->makeAdmission($this->tenantB);

        $this->assertFalse($policy->delete($this->adminA, $admission));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Super-admin Gate::before bypass
    // ══════════════════════════════════════════════════════════════════════════

    public function test_super_admin_bypasses_student_policy_via_gate(): void
    {
        $student = $this->makeStudent($this->tenantA);

        // Gate::before returns true for super_admin — so all checks pass
        $this->actingAs($this->superAdmin);
        $this->assertTrue(Gate::allows('update', $student));
        $this->assertTrue(Gate::allows('delete', $student));
    }

    public function test_super_admin_bypasses_teacher_policy_via_gate(): void
    {
        $teacher = Teacher::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->superAdmin);
        $this->assertTrue(Gate::allows('update', $teacher));
        $this->assertTrue(Gate::allows('delete', $teacher));
    }

    public function test_super_admin_bypasses_fee_policy_via_gate(): void
    {
        $fee = $this->makeFee($this->tenantA);

        $this->actingAs($this->superAdmin);
        $this->assertTrue(Gate::allows('update', $fee));
        $this->assertTrue(Gate::allows('delete', $fee));
    }

    public function test_cross_tenant_admin_is_denied_via_gate(): void
    {
        $student = $this->makeStudent($this->tenantB); // belongs to B

        $this->actingAs($this->adminA); // admin of A
        $this->assertFalse(Gate::allows('update', $student));
        $this->assertFalse(Gate::allows('delete', $student));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function makeStudent(Tenant $tenant): Student
    {
        $class = SchoolClass::factory()->create(['tenant_id' => $tenant->id]);

        return Student::factory()->create([
            'tenant_id'       => $tenant->id,
            'school_class_id' => $class->id,
        ]);
    }

    private function makeFee(Tenant $tenant): Fee
    {
        $class   = SchoolClass::factory()->create(['tenant_id' => $tenant->id]);
        $student = Student::factory()->create([
            'tenant_id'       => $tenant->id,
            'school_class_id' => $class->id,
        ]);

        return Fee::create([
            'tenant_id'   => $tenant->id,
            'student_id'  => $student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 500,
            'amount_paid' => 0,
        ]);
    }

    private function makeAdmission(Tenant $tenant): Admission
    {
        return Admission::create([
            'tenant_id'          => $tenant->id,
            'first_name'         => 'Kofi',
            'last_name'          => 'Test',
            'gender'             => 'male',
            'guardian_name'      => 'Parent',
            'guardian_phone'     => '0241234567',
            'class_applying_for' => 'Basic 1',
            'status'             => 'pending',
            'submitted_at'       => now(),
        ]);
    }
}
