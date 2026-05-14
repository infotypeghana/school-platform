<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Tenant;
use App\Services\GradeCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        $caScore   = fake()->randomFloat(1, 0, 30);
        $examScore = fake()->randomFloat(1, 0, 70);

        return [
            'tenant_id'       => Tenant::factory(),
            'student_id'      => Student::factory(),
            'subject_id'      => Subject::factory(),
            'school_class_id' => SchoolClass::factory(),
            'term_id'         => AcademicTerm::factory(),
            'ca_score'        => $caScore,
            'exam_score'      => $examScore,
            // total_score and grade are computed by model booted() hook
        ];
    }

    /** Assessment with a specific total score (splits CA/exam proportionally). */
    public function withTotal(float $total): static
    {
        return $this->state(function () use ($total) {
            $ca   = min(30, round($total * 0.3, 1));
            $exam = min(70, round($total - $ca,  1));
            return ['ca_score' => $ca, 'exam_score' => $exam];
        });
    }

    /** Passing assessment (>= 45, C6 or better). */
    public function passing(): static
    {
        return $this->withTotal(fake()->randomFloat(1, 45, 100));
    }

    /** Failing assessment (< 35). */
    public function failing(): static
    {
        return $this->withTotal(fake()->randomFloat(1, 0, 34));
    }
}
