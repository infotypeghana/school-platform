<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    /** GES basic school class names (Ghana Education Service) */
    private static array $gesClasses = [
        'Basic 1', 'Basic 2', 'Basic 3',
        'Basic 4', 'Basic 5', 'Basic 6',
        'JHS 1', 'JHS 2', 'JHS 3',
        'KG 1', 'KG 2',
    ];

    public function definition(): array
    {
        return [
            'tenant_id'        => Tenant::factory(),
            'name'             => fake()->randomElement(self::$gesClasses),
            'level'            => fake()->randomElement(['Lower Primary', 'Upper Primary', 'JHS', 'Kindergarten']),
            'section'          => fake()->optional(0.4)->randomElement(['A', 'B', 'C']),
            'class_teacher_id' => null,
        ];
    }
}
