<?php

namespace Tests\Feature\Admin;

use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;

class AssessmentTest extends AdminTestCase
{
    private SchoolClass $class;
    private Student $student;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 4',
        ]);

        $this->student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $this->subject = Subject::create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'name'            => 'Mathematics',
            'is_core'         => true,
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->asAdmin()->get('/report-cards/scores');

        $response->assertOk();
        $response->assertViewIs('admin.assessments.index');
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $this->asGuest()->get('/report-cards/scores')->assertRedirect();
    }

    // ── Edit (score entry grid) ───────────────────────────────────────────────

    public function test_edit_returns_200_with_valid_params(): void
    {
        $response = $this->asAdmin()->get(
            "/report-cards/scores/edit?class_id={$this->class->id}&term_id={$this->term->id}"
        );

        $response->assertOk();
        $response->assertViewIs('admin.assessments.edit');
    }

    public function test_edit_requires_class_id(): void
    {
        $response = $this->asAdmin()->get("/report-cards/scores/edit?term_id={$this->term->id}");

        $response->assertSessionHasErrors('class_id');
    }

    public function test_edit_requires_term_id(): void
    {
        $response = $this->asAdmin()->get("/report-cards/scores/edit?class_id={$this->class->id}");

        $response->assertSessionHasErrors('term_id');
    }

    public function test_edit_passes_students_and_subjects_to_view(): void
    {
        $response = $this->asAdmin()->get(
            "/report-cards/scores/edit?class_id={$this->class->id}&term_id={$this->term->id}"
        );

        $response->assertViewHas('students');
        $response->assertViewHas('subjects');
        $response->assertViewHas('existing');
    }

    // ── Update (bulk-save scores) ─────────────────────────────────────────────

    public function test_update_creates_assessment_record(): void
    {
        $response = $this->asAdmin()->put('/report-cards/scores/update', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
            'scores'   => [
                $this->student->id => [
                    $this->subject->id => ['ca' => '25', 'exam' => '60'],
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('assessments', [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'term_id'    => $this->term->id,
            'ca_score'   => 25,
            'exam_score' => 60,
        ]);
    }

    public function test_update_overwrites_existing_score(): void
    {
        // Initial score
        Assessment::create([
            'tenant_id'       => $this->tenant->id,
            'student_id'      => $this->student->id,
            'subject_id'      => $this->subject->id,
            'term_id'         => $this->term->id,
            'school_class_id' => $this->class->id,
            'ca_score'        => 10,
            'exam_score'      => 40,
        ]);

        // Re-save
        $this->asAdmin()->put('/report-cards/scores/update', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
            'scores'   => [
                $this->student->id => [
                    $this->subject->id => ['ca' => '28', 'exam' => '65'],
                ],
            ],
        ]);

        // Only one record should exist with updated scores
        $this->assertSame(
            1,
            Assessment::where('student_id', $this->student->id)
                ->where('subject_id', $this->subject->id)
                ->where('term_id', $this->term->id)
                ->count()
        );
        $this->assertDatabaseHas('assessments', ['ca_score' => 28, 'exam_score' => 65]);
    }

    public function test_update_skips_empty_score_rows(): void
    {
        $this->asAdmin()->put('/report-cards/scores/update', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
            'scores'   => [
                $this->student->id => [
                    $this->subject->id => ['ca' => '', 'exam' => ''],
                ],
            ],
        ]);

        $this->assertDatabaseMissing('assessments', [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
        ]);
    }

    public function test_update_rejects_ca_score_above_30(): void
    {
        $response = $this->asAdmin()->put('/report-cards/scores/update', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
            'scores'   => [
                $this->student->id => [
                    $this->subject->id => ['ca' => '35', 'exam' => '60'],
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_update_rejects_exam_score_above_70(): void
    {
        $response = $this->asAdmin()->put('/report-cards/scores/update', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
            'scores'   => [
                $this->student->id => [
                    $this->subject->id => ['ca' => '25', 'exam' => '75'],
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_update_redirects_back_to_edit_grid(): void
    {
        $response = $this->asAdmin()->put('/report-cards/scores/update', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
            'scores'   => [
                $this->student->id => [
                    $this->subject->id => ['ca' => '20', 'exam' => '55'],
                ],
            ],
        ]);

        $response->assertRedirectContains('/report-cards/scores/edit');
    }
}
