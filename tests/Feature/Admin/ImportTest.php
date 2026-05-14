<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImportTest extends AdminTestCase
{
    // ── Student import ────────────────────────────────────────────────────────

    public function test_student_import_form_returns_200(): void
    {
        $this->asAdmin()->get('/students/import')
            ->assertOk()
            ->assertViewIs('admin.students.import');
    }

    public function test_student_template_download(): void
    {
        $this->asAdmin()->get('/students/import/template')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_student_import_creates_students(): void
    {
        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Basic 4']);

        $csv = implode("\n", [
            'first_name,last_name,date_of_birth,gender,class_name,guardian_name,guardian_phone,guardian_email,address,admission_date,status',
            "Kofi,Mensah,2012-05-20,male,Basic 4,Ama Mensah,0241234567,ama@gmail.com,Accra,2024-09-01,active",
            "Abena,Owusu,2011-03-15,female,Basic 4,Kwame Owusu,0501234567,,,2024-09-01,active",
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $this->asAdmin()->post('/students/import', ['csv_file' => $file])
            ->assertRedirect('/students')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('students', ['first_name' => 'Kofi', 'last_name' => 'Mensah']);
        $this->assertDatabaseHas('students', ['first_name' => 'Abena', 'last_name' => 'Owusu']);
    }

    public function test_student_import_reports_validation_errors(): void
    {
        $csv = implode("\n", [
            'first_name,last_name,date_of_birth,gender,class_name,guardian_name,guardian_phone',
            ",Mensah,2012-05-20,male,,Ama Mensah,0241234567",  // missing first_name
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $this->asAdmin()->post('/students/import', ['csv_file' => $file])
            ->assertRedirect()
            ->assertSessionHas('import_errors');
    }

    public function test_student_import_rejects_invalid_class(): void
    {
        $csv = implode("\n", [
            'first_name,last_name,date_of_birth,gender,class_name,guardian_name,guardian_phone',
            "Kofi,Mensah,2012-05-20,male,Nonexistent Class,Ama Mensah,0241234567",
        ]);

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $this->asAdmin()->post('/students/import', ['csv_file' => $file])
            ->assertRedirect()
            ->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('students', ['first_name' => 'Kofi']);
    }

    public function test_student_import_requires_csv_file(): void
    {
        $this->asAdmin()->post('/students/import', [])
            ->assertSessionHasErrors('csv_file');
    }

    // ── Teacher import ────────────────────────────────────────────────────────

    public function test_teacher_import_form_returns_200(): void
    {
        $this->asAdmin()->get('/teachers/import')
            ->assertOk()
            ->assertViewIs('admin.teachers.import');
    }

    public function test_teacher_template_download(): void
    {
        $this->asAdmin()->get('/teachers/import/template')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_teacher_import_creates_teachers(): void
    {
        $csv = implode("\n", [
            'first_name,last_name,email,phone,gender,qualification,specialization,joined_date',
            "Abena,Owusu,abena@school.edu.gh,0241234567,female,B.Ed Mathematics,Mathematics,2020-01-10",
            "Kwame,Boateng,kwame@school.edu.gh,0501234567,male,B.Ed English,English,2021-03-01",
        ]);

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $this->asAdmin()->post('/teachers/import', ['csv_file' => $file])
            ->assertRedirect('/teachers')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('teachers', ['email' => 'abena@school.edu.gh']);
        $this->assertDatabaseHas('teachers', ['email' => 'kwame@school.edu.gh']);
    }

    public function test_teacher_import_rejects_duplicate_email(): void
    {
        Teacher::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email'     => 'existing@school.edu.gh',
        ]);

        $csv = implode("\n", [
            'first_name,last_name,email,phone',
            "Duplicate,Teacher,existing@school.edu.gh,0241234567",
        ]);

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $this->asAdmin()->post('/teachers/import', ['csv_file' => $file])
            ->assertRedirect()
            ->assertSessionHas('import_errors');
    }

    public function test_teacher_import_validates_required_fields(): void
    {
        $csv = implode("\n", [
            'first_name,last_name,email,phone',
            ",Owusu,,0241234567",  // missing first_name and email
        ]);

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $this->asAdmin()->post('/teachers/import', ['csv_file' => $file])
            ->assertRedirect()
            ->assertSessionHas('import_errors');
    }
}
