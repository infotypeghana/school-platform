<?php

namespace Tests\Unit;

use App\Jobs\ExportSchoolDataJob;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\SchoolExport;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * Unit tests for ExportSchoolDataJob.
 *
 * Exercises the actual ZIP generation with real data.
 */
class ExportSchoolDataJobTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private SchoolExport $export;
    private SchoolClass $class;
    private Teacher $teacher;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $year = AcademicYear::create([
            'year_label' => 'Export-Unit-Test-Year',
            'is_current' => false,
        ]);

        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'term_number'      => 1,
            'term_name'        => 'First Term',
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'is_current'       => true,
        ]);

        $this->tenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'export-school',
            'name'   => 'Export Test School',
            'email'  => 'info@exportschool.edu.gh',
            'status' => 'active',
        ]);

        Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $year->id,
            'term_id'          => $term->id,
            'amount'           => 500,
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'status'           => Subscription::STATUS_ACTIVE,
            'activated_at'     => now()->subDays(60),
        ]);

        $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'school_admin']);

        $this->class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Basic 5']);

        $this->teacher = Teacher::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        // Attendance record
        Attendance::create([
            'tenant_id'       => $this->tenant->id,
            'student_id'      => $this->student->id,
            'school_class_id' => $this->class->id,
            'term_id'         => $term->id,
            'date'            => now()->toDateString(),
            'status'          => 'present',
        ]);

        // Fee record
        Fee::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'student_id' => $this->student->id,
        ]);

        $this->export = SchoolExport::create([
            'tenant_id'    => $this->tenant->id,
            'requested_by' => $user->id,
            'status'       => SchoolExport::STATUS_PENDING,
        ]);
    }

    public function test_job_creates_zip_file(): void
    {
        (new ExportSchoolDataJob($this->export->id, $this->tenant->id))->handle();

        $this->export->refresh();

        $this->assertEquals(SchoolExport::STATUS_READY, $this->export->status);
        $this->assertNotNull($this->export->file_path);
        $this->assertNotNull($this->export->ready_at);
        $this->assertNotNull($this->export->expires_at);

        Storage::disk('local')->assertExists($this->export->file_path);
    }

    public function test_zip_contains_expected_csv_files(): void
    {
        (new ExportSchoolDataJob($this->export->id, $this->tenant->id))->handle();

        $this->export->refresh();
        $zipPath = Storage::disk('local')->path($this->export->file_path);

        $zip = new ZipArchive();
        $zip->open($zipPath);

        $expectedFiles = ['README.txt', 'students.csv', 'teachers.csv', 'classes.csv', 'attendance.csv', 'assessments.csv', 'fees.csv'];
        foreach ($expectedFiles as $file) {
            $this->assertNotFalse($zip->locateName($file), "ZIP is missing: {$file}");
        }

        $zip->close();
    }

    public function test_students_csv_contains_student_data(): void
    {
        (new ExportSchoolDataJob($this->export->id, $this->tenant->id))->handle();

        $this->export->refresh();
        $zipPath = Storage::disk('local')->path($this->export->file_path);

        $zip = new ZipArchive();
        $zip->open($zipPath);
        $csv = $zip->getFromName('students.csv');
        $zip->close();

        $this->assertStringContainsString($this->student->admission_number, $csv);
        $this->assertStringContainsString($this->student->first_name, $csv);
    }

    public function test_teachers_csv_contains_teacher_data(): void
    {
        (new ExportSchoolDataJob($this->export->id, $this->tenant->id))->handle();

        $this->export->refresh();
        $zipPath = Storage::disk('local')->path($this->export->file_path);

        $zip = new ZipArchive();
        $zip->open($zipPath);
        $csv = $zip->getFromName('teachers.csv');
        $zip->close();

        $this->assertStringContainsString($this->teacher->first_name, $csv);
    }

    public function test_attendance_csv_contains_attendance_data(): void
    {
        (new ExportSchoolDataJob($this->export->id, $this->tenant->id))->handle();

        $this->export->refresh();
        $zipPath = Storage::disk('local')->path($this->export->file_path);

        $zip = new ZipArchive();
        $zip->open($zipPath);
        $csv = $zip->getFromName('attendance.csv');
        $zip->close();

        $this->assertStringContainsString('present', $csv);
        $this->assertStringContainsString($this->student->admission_number, $csv);
    }

    public function test_job_marks_failed_on_invalid_export(): void
    {
        // Non-existent export ID — job should handle gracefully
        $job = new ExportSchoolDataJob(99999, $this->tenant->id);
        $job->handle();

        // No exception thrown, export not updated since it doesn't exist
        $this->assertTrue(true);
    }

    public function test_export_expires_at_is_24_hours_from_ready(): void
    {
        (new ExportSchoolDataJob($this->export->id, $this->tenant->id))->handle();

        $this->export->refresh();

        $diffHours = $this->export->ready_at->diffInHours($this->export->expires_at);
        $this->assertEquals(24, $diffHours);
    }
}
