<?php

namespace Tests\Feature\Website;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Facades\Hash;

/**
 * Teacher portal: login, logout, dashboard, score entry, timetable.
 */
class TeacherPortalTest extends WebsiteTestCase
{
    private Teacher $teacher;
    private SchoolClass $class;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 5',
        ]);

        $this->teacher = Teacher::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'email'           => 'kwame.teacher@school.gh',
            'portal_active'   => true,
            'portal_password' => Hash::make('Teacher@123'),
        ]);

        $this->subject = Subject::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'teacher_id'      => $this->teacher->id,
            'name'            => 'Mathematics',
        ]);

        // Assign class teacher
        $this->class->update(['class_teacher_id' => $this->teacher->id]);
    }

    // ── Login page ────────────────────────────────────────────────────────────

    public function test_login_page_is_accessible(): void
    {
        $this->get('/teacher/login')->assertOk();
    }

    public function test_login_page_shows_school_name(): void
    {
        $this->get('/teacher/login')->assertSee($this->tenant->name);
    }

    // ── Authentication ────────────────────────────────────────────────────────

    public function test_valid_credentials_log_in_and_redirect_to_dashboard(): void
    {
        $response = $this->post('/teacher/login', [
            'email'    => 'kwame.teacher@school.gh',
            'password' => 'Teacher@123',
        ]);

        $response->assertRedirect('/teacher/dashboard');
        $this->assertEquals($this->teacher->id, session('teacher_portal_id'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $response = $this->post('/teacher/login', [
            'email'    => 'kwame.teacher@school.gh',
            'password' => 'WrongPass!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertNull(session('teacher_portal_id'));
    }

    public function test_inactive_portal_is_rejected(): void
    {
        $this->teacher->update(['portal_active' => false]);

        $response = $this->post('/teacher/login', [
            'email'    => 'kwame.teacher@school.gh',
            'password' => 'Teacher@123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_unknown_email_is_rejected(): void
    {
        $response = $this->post('/teacher/login', [
            'email'    => 'nobody@school.gh',
            'password' => 'Teacher@123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function test_dashboard_redirects_when_not_authenticated(): void
    {
        $this->get('/teacher/dashboard')->assertRedirect('/teacher/login');
    }

    public function test_authenticated_teacher_sees_dashboard(): void
    {
        $response = $this->withSession(['teacher_portal_id' => $this->teacher->id])
            ->get('/teacher/dashboard');

        $response->assertOk();
        $response->assertViewIs('teacher-portal.dashboard');
        $response->assertSee($this->teacher->first_name);
    }

    public function test_dashboard_shows_assigned_class(): void
    {
        $response = $this->withSession(['teacher_portal_id' => $this->teacher->id])
            ->get('/teacher/dashboard');

        $response->assertSee('Basic 5');
    }

    // ── Score entry ───────────────────────────────────────────────────────────

    public function test_scores_index_requires_auth(): void
    {
        $this->get('/teacher/scores')->assertRedirect('/teacher/login');
    }

    public function test_scores_index_shows_teacher_subjects(): void
    {
        $response = $this->withSession(['teacher_portal_id' => $this->teacher->id])
            ->get('/teacher/scores');

        $response->assertOk();
        $response->assertSee('Mathematics');
    }

    public function test_score_entry_grid_loads_for_own_subject(): void
    {
        $response = $this->withSession(['teacher_portal_id' => $this->teacher->id])
            ->get('/teacher/scores/edit?' . http_build_query([
                'subject_id' => $this->subject->id,
                'term_id'    => $this->term->id,
            ]));

        $response->assertOk();
        $response->assertViewIs('teacher-portal.scores.edit');
    }

    public function test_teacher_cannot_enter_scores_for_other_subject(): void
    {
        // A subject that belongs to a different teacher
        $otherSubject = Subject::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'teacher_id'      => Teacher::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'name'            => 'Science',
        ]);

        $response = $this->withSession(['teacher_portal_id' => $this->teacher->id])
            ->get('/teacher/scores/edit?' . http_build_query([
                'subject_id' => $otherSubject->id,
                'term_id'    => $this->term->id,
            ]));

        $response->assertStatus(404);
    }

    public function test_teacher_can_save_scores(): void
    {
        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $response = $this->withSession(['teacher_portal_id' => $this->teacher->id])
            ->put('/teacher/scores', [
                'subject_id' => $this->subject->id,
                'term_id'    => $this->term->id,
                'scores'     => [
                    $student->id => ['ca' => '25', 'exam' => '60'],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'student_id' => $student->id,
            'subject_id' => $this->subject->id,
            'term_id'    => $this->term->id,
            'ca_score'   => 25,
            'exam_score' => 60,
        ]);
    }

    // ── Timetable ─────────────────────────────────────────────────────────────

    public function test_timetable_requires_auth(): void
    {
        $this->get('/teacher/timetable')->assertRedirect('/teacher/login');
    }

    public function test_timetable_renders_for_authenticated_teacher(): void
    {
        $response = $this->withSession(['teacher_portal_id' => $this->teacher->id])
            ->get('/teacher/timetable');

        $response->assertOk();
        $response->assertViewIs('teacher-portal.timetable');
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_logout_clears_session_and_redirects(): void
    {
        $response = $this->withSession(['teacher_portal_id' => $this->teacher->id])
            ->post('/teacher/logout');

        $response->assertRedirect('/teacher/login');
        $this->assertNull(session('teacher_portal_id'));
    }

    // ── Last login timestamp ──────────────────────────────────────────────────

    public function test_successful_login_records_last_login_time(): void
    {
        $this->assertNull($this->teacher->portal_last_login);

        $this->post('/teacher/login', [
            'email'    => 'kwame.teacher@school.gh',
            'password' => 'Teacher@123',
        ]);

        $this->teacher->refresh();
        $this->assertNotNull($this->teacher->portal_last_login);
    }
}
