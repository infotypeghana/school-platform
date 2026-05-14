<?php

namespace Tests\Feature\Admin;

use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;

class AnalyticsTest extends AdminTestCase
{
    public function test_analytics_returns_200(): void
    {
        $this->asAdmin()->get('/analytics')
            ->assertOk()
            ->assertViewIs('admin.analytics.index');
    }

    public function test_analytics_filters_by_term(): void
    {
        $this->asAdmin()->get('/analytics?term_id=' . $this->term->id)->assertOk();
    }

    public function test_analytics_filters_by_class(): void
    {
        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->asAdmin()->get('/analytics?class_id=' . $class->id)->assertOk();
    }

    public function test_analytics_contains_pass_rate_with_data(): void
    {
        $class   = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);
        $student = Student::factory()->create(['tenant_id' => $this->tenant->id, 'school_class_id' => $class->id]);
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id, 'school_class_id' => $class->id]);

        Assessment::create([
            'tenant_id'       => $this->tenant->id,
            'student_id'      => $student->id,
            'subject_id'      => $subject->id,
            'school_class_id' => $class->id,
            'term_id'         => $this->term->id,
            'ca_score'        => 25,
            'exam_score'      => 60,
            'total_score'     => 85,
            'grade'           => 'A1',
        ]);

        $response = $this->asAdmin()->get('/analytics?term_id=' . $this->term->id);
        $response->assertOk();
        $response->assertViewHas('passRate');
        $response->assertViewHas('subjectPerformance');
    }

    public function test_analytics_shows_student_counts(): void
    {
        $response = $this->asAdmin()->get('/analytics');
        $response->assertViewHas('studentCounts', fn ($v) => isset($v['total']));
    }

    public function test_guest_redirected_from_analytics(): void
    {
        $this->asGuest()->get('/analytics')->assertRedirect();
    }
}
