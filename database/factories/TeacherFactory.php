<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'tenant_id'      => Tenant::factory(),
            'first_name'     => $gender === 'male' ? fake()->firstNameMale() : fake()->firstNameFemale(),
            'last_name'      => fake()->lastName(),
            'staff_id'       => 'TCH-' . strtoupper(fake()->bothify('####??')),
            'email'          => fake()->unique()->safeEmail(),
            'phone'          => fake()->phoneNumber(),
            'gender'         => $gender,
            'qualification'  => fake()->randomElement([
                'B.Ed (Basic Education)', 'B.Ed (Mathematics)',
                'B.Ed (English)', 'Diploma in Education',
                'M.Ed (Curriculum Studies)', 'B.A (Social Studies)',
            ]),
            'specialization' => fake()->randomElement([
                'Mathematics', 'English Language', 'Science',
                'Social Studies', 'ICT', 'French',
                'Religious & Moral Education', 'Creative Arts',
            ]),
            'joined_date'    => fake()->dateTimeBetween('-10 years', '-1 month'),
            'status'         => 'active',
        ];
    }

    /** Mark teacher as inactive. */
    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
