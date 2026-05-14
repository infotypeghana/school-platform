<?php

namespace Tests\Feature\Admin;

use App\Models\Admission;
use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\Hash;

class DashboardTest extends AdminTestCase
{
    // ── Authentication guard ──────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->asGuest()->get('/dashboard');

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location') ?? '');
    }

    // ── Basic 200 ─────────────────────────────────────────────────────────────

    public function test_authenticated_admin_sees_dashboard(): void
    {
        $response = $this->asAdmin()->get('/dashboard');

        $response->assertOk();
    }

    // ── View variables ────────────────────────────────────────────────────────

    public function test_dashboard_passes_correct_student_count(): void
    {
        // 3 active students for this tenant, 1 inactive
        Student::factory()->count(3)->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => SchoolClass::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'status'          => 'active',
        ]);
        Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => SchoolClass::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'status'          => 'graduated',
        ]);

        $response = $this->asAdmin()->get('/dashboard');

        $response->assertViewHas('totalStudents', 3);
    }

    public function test_dashboard_passes_correct_teacher_count(): void
    {
        Teacher::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        Teacher::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'inactive',
        ]);

        $response = $this->asAdmin()->get('/dashboard');

        $response->assertViewHas('totalTeachers', 2);
    }

    public function test_dashboard_passes_correct_class_count(): void
    {
        SchoolClass::factory()->count(4)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->asAdmin()->get('/dashboard');

        $response->assertViewHas('totalClasses', 4);
    }

    public function test_dashboard_passes_pending_admissions_count(): void
    {
        Admission::create([
            'tenant_id'         => $this->tenant->id,
            'first_name'        => 'Kofi',
            'last_name'         => 'Mensah',
            'gender'            => 'male',
            'guardian_name'     => 'Ama Mensah',
            'guardian_phone'    => '0241234567',
            'class_applying_for'=> 'Basic 3',
            'status'            => 'pending',
            'submitted_at'      => now(),
        ]);
        Admission::create([
            'tenant_id'         => $this->tenant->id,
            'first_name'        => 'Ama',
            'last_name'         => 'Boateng',
            'gender'            => 'female',
            'guardian_name'     => 'Yaw Boateng',
            'guardian_phone'    => '0241234568',
            'class_applying_for'=> 'Basic 1',
            'status'            => 'accepted', // not pending
            'submitted_at'      => now(),
        ]);

        $response = $this->asAdmin()->get('/dashboard');

        $response->assertViewHas('pendingAdmissions', 1);
    }

    public function test_dashboard_fees_collected_scoped_to_current_term(): void
    {
        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);
        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
        ]);

        // Fee for current term
        Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 500,
            'amount_paid' => 300,
        ]);

        $response = $this->asAdmin()->get('/dashboard');

        $response->assertViewHas('feesCollected', 300.0);
    }

    public function test_dashboard_passes_term_progress_variables(): void
    {
        $response = $this->asAdmin()->get('/dashboard');

        $response->assertViewHas('term');
        $response->assertViewHas('termProgress');
        $response->assertViewHas('daysLeft');

        $termProgress = $response->viewData('termProgress');
        $this->assertIsInt($termProgress);
        $this->assertGreaterThanOrEqual(0, $termProgress);
        $this->assertLessThanOrEqual(100, $termProgress);
    }
}
