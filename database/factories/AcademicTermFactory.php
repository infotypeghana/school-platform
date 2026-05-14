<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
{
    protected $model = AcademicTerm::class;

    public function definition(): array
    {
        $termNumber = fake()->numberBetween(1, 3);
        $start      = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'academic_year_id' => AcademicYear::factory(),
            'term_number'      => $termNumber,
            'term_name'        => match ($termNumber) {
                1       => 'First Term',
                2       => 'Second Term',
                default => 'Third Term',
            },
            'start_date' => $start,
            'end_date'   => (clone $start)->modify('+90 days'),
            'is_current' => false,
        ];
    }
}
