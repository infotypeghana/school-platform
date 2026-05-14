<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company() . ' Academy';
        $slug = Str::slug($name) . '-' . Str::random(4);

        return [
            'uuid'    => Str::uuid(),
            'slug'    => $slug,
            'name'    => $name,
            'email'   => fake()->unique()->safeEmail(),
            'phone'   => fake()->phoneNumber(),
            'address' => fake()->address(),
            'status'  => 'trial',
            'logo'    => null,
        ];
    }

    /** Set tenant as active (paid subscription). */
    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    /** Set tenant into grace period. */
    public function grace(): static
    {
        return $this->state(fn () => ['status' => 'grace']);
    }

    /** Set tenant as locked. */
    public function locked(): static
    {
        return $this->state(fn () => ['status' => 'locked']);
    }

    /** Set tenant as suspended. */
    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }
}
