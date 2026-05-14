<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => Tenant::factory(),
            'school_class_id' => SchoolClass::factory(),
            'teacher_id'      => null,
            'name'            => $this->faker->unique()->word() . ' Studies',
            'code'            => strtoupper($this->faker->unique()->lexify('???')),
            'is_core'         => false,
        ];
    }
}
