<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\Fee;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fee>
 */
class FeeFactory extends Factory
{
    protected $model = Fee::class;

    public function definition(): array
    {
        $amount     = fake()->randomElement([200, 350, 500, 750, 1000]);
        $amountPaid = 0;

        return [
            'tenant_id'      => Tenant::factory(),
            'student_id'     => Student::factory(),
            'term_id'        => AcademicTerm::factory(),
            'fee_type'       => fake()->randomElement(['tuition', 'feeding', 'pta', 'uniform', 'examination']),
            'amount'         => $amount,
            'amount_paid'    => $amountPaid,
            // balance & status computed by model booted() — no need to set here
        ];
    }

    /** Fully paid fee. */
    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            return ['amount_paid' => $attributes['amount']];
        });
    }

    /** Partially paid fee. */
    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            return ['amount_paid' => round($attributes['amount'] * 0.5, 2)];
        });
    }

    /** Unpaid fee (default). */
    public function unpaid(): static
    {
        return $this->state(fn () => ['amount_paid' => 0]);
    }
}
