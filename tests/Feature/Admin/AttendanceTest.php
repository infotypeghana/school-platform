<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;

class AttendanceTest extends AdminTestCase
{
    private SchoolClass $class;
    private Student $student1;
    private Student $student2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 3',
        ]);

        $this->student1 = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $this->student2 = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->asAdmin()->get('/attendance');

        $response->assertOk();
        $response->assertViewIs('admin.attendance.index');
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $this->asGuest()->get('/attendance')->assertRedirect();
    }

    // ── Sheet ─────────────────────────────────────────────────────────────────

    public function test_sheet_returns_200_with_valid_params(): void
    {
        $date = now()->subDay()->toDateString();
        $response = $this->asAdmin()->get("/attendance/sheet?class_id={$this->class->id}&date={$date}");

        $response->assertOk();
        $response->assertViewIs('admin.attendance.sheet');
    }

    public function test_sheet_requires_class_id(): void
    {
        $date = now()->subDay()->toDateString();
        $response = $this->asAdmin()->get("/attendance/sheet?date={$date}");

        $response->assertSessionHasErrors('class_id');
    }

    public function test_sheet_requires_date(): void
    {
        $response = $this->asAdmin()->get("/attendance/sheet?class_id={$this->class->id}");

        $response->assertSessionHasErrors('date');
    }

    public function test_sheet_rejects_future_date(): void
    {
        $date = now()->addDay()->toDateString();
        $response = $this->asAdmin()->get("/attendance/sheet?class_id={$this->class->id}&date={$date}");

        $response->assertSessionHasErrors('date');
    }

    public function test_sheet_passes_class_and_students_to_view(): void
    {
        $date = now()->subDay()->toDateString();
        $response = $this->asAdmin()->get("/attendance/sheet?class_id={$this->class->id}&date={$date}");

        $response->assertViewHas('class');
        $response->assertViewHas('existing');
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function test_save_creates_attendance_records(): void
    {
        $date = now()->subDay()->toDateString();

        $response = $this->asAdmin()->post('/attendance/save', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
            'date'     => $date,
            'attendance' => [
                $this->student1->id => 'present',
                $this->student2->id => 'absent',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // SQLite stores date columns with a time component; match on student+status only
        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->student1->id,
            'status'     => 'present',
        ]);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->student2->id,
            'status'     => 'absent',
        ]);
    }

    public function test_save_all_four_statuses_in_one_request(): void
    {
        // Create two extra students so we have four in this class
        $student3 = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);
        $student4 = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $response = $this->asAdmin()->post('/attendance/save', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
            'date'     => now()->subDay()->toDateString(),
            'attendance' => [
                $this->student1->id => 'present',
                $this->student2->id => 'absent',
                $student3->id       => 'late',
                $student4->id       => 'excused',
            ],
        ]);

        $response->assertRedirect();

        foreach ([
            [$this->student1->id, 'present'],
            [$this->student2->id, 'absent'],
            [$student3->id, 'late'],
            [$student4->id, 'excused'],
        ] as [$studentId, $status]) {
            $this->assertDatabaseHas('attendances', [
                'student_id' => $studentId,
                'status'     => $status,
            ]);
        }
    }

    public function test_save_records_late_status(): void
    {
        $response = $this->asAdmin()->post('/attendance/save', [
            'class_id'   => $this->class->id,
            'term_id'    => $this->term->id,
            'date'       => now()->subDays(2)->toDateString(),
            'attendance' => [$this->student1->id => 'late'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->student1->id,
            'status'     => 'late',
        ]);
    }

    public function test_save_records_excused_status(): void
    {
        $response = $this->asAdmin()->post('/attendance/save', [
            'class_id'   => $this->class->id,
            'term_id'    => $this->term->id,
            'date'       => now()->subDays(3)->toDateString(),
            'attendance' => [$this->student2->id => 'excused'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->student2->id,
            'status'     => 'excused',
        ]);
    }

    public function test_save_rejects_invalid_status(): void
    {
        $response = $this->asAdmin()->post('/attendance/save', [
            'class_id'   => $this->class->id,
            'date'       => now()->subDay()->toDateString(),
            'attendance' => [$this->student1->id => 'invalid'],
        ]);

        $response->assertSessionHasErrors('attendance.*');
    }

    // ── Report ────────────────────────────────────────────────────────────────

    public function test_report_returns_200_with_valid_params(): void
    {
        $start = now()->subDays(7)->toDateString();
        $end   = now()->toDateString();
        $response = $this->asAdmin()->get(
            "/attendance/report?class_id={$this->class->id}&start_date={$start}&end_date={$end}"
        );

        $response->assertOk();
        $response->assertViewIs('admin.attendance.report');
    }

    public function test_report_passes_summary_with_per_student_counts(): void
    {
        $date = now()->subDays(3)->toDateString();

        Attendance::create([
            'tenant_id'       => $this->tenant->id,
            'student_id'      => $this->student1->id,
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
            'date'            => $date,
            'status'          => 'present',
        ]);

        $start = now()->subDays(7)->toDateString();
        $end   = now()->toDateString();
        $response = $this->asAdmin()->get(
            "/attendance/report?class_id={$this->class->id}&start_date={$start}&end_date={$end}"
        );

        $summary = $response->viewData('summary');
        $this->assertArrayHasKey($this->student1->id, $summary);
        $this->assertSame(1, $summary[$this->student1->id]['present']);
    }

    public function test_report_requires_class_id(): void
    {
        $start = now()->subDays(7)->toDateString();
        $end   = now()->toDateString();
        $response = $this->asAdmin()->get("/attendance/report?start_date={$start}&end_date={$end}");

        $response->assertSessionHasErrors('class_id');
    }

    public function test_report_requires_end_after_start(): void
    {
        $start = now()->toDateString();
        $end   = now()->subDays(5)->toDateString();
        $response = $this->asAdmin()->get(
            "/attendance/report?class_id={$this->class->id}&start_date={$start}&end_date={$end}"
        );

        $response->assertSessionHasErrors('end_date');
    }
}
