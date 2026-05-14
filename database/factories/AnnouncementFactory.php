<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'tenant_id'      => Tenant::factory(),
            'created_by'     => User::factory(),
            'title'          => fake()->sentence(6),
            'body'           => fake()->paragraphs(3, true),
            'audience'       => fake()->randomElement(['all', 'teachers', 'parents']),
            'school_class_id'=> null,
            'is_pinned'      => false,
            'published_at'   => now()->subHour(),
            'expires_at'     => null,
        ];
    }

    /** Pinned announcement. */
    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }

    /** Expired announcement. */
    public function expired(): static
    {
        return $this->state(fn () => [
            'published_at' => now()->subDays(10),
            'expires_at'   => now()->subDay(),
        ]);
    }

    /** Scheduled (not yet published). */
    public function scheduled(): static
    {
        return $this->state(fn () => [
            'published_at' => now()->addDay(),
            'expires_at'   => now()->addWeek(),
        ]);
    }

    /** Audience targeting a specific audience string. */
    public function forAudience(string $audience): static
    {
        return $this->state(fn () => ['audience' => $audience]);
    }
}
