<?php

namespace Database\Factories;

use App\Models\BiometricDevice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiometricDevice>
 */
class BiometricDeviceFactory extends Factory
{
    protected $model = BiometricDevice::class;

    public function definition(): array
    {
        return [
            'tenant_id'     => Tenant::factory(),
            'name'          => fake()->randomElement(['Main Gate', 'Staff Room', 'Admin Block', 'Library']) . ' Scanner',
            'device_serial' => 'SN-' . strtoupper(fake()->bothify('??##-####')),
            'ip_address'    => fake()->localIpv4(),
            'port'          => 4370,
            'password'      => 0,
            'model'         => fake()->optional(0.6)->randomElement(['ZK-K40', 'ZK-F18', 'ZK-UA300', 'ZK-iClock700']),
            'location'      => fake()->optional(0.7)->randomElement(['Main Entrance', 'Back Gate', 'Staff Room', 'Admin Block']),
            'is_active'     => true,
            'last_sync_at'  => null,
        ];
    }

    /** Inactive device. */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** Device that has been synced recently. */
    public function synced(): static
    {
        return $this->state(fn () => ['last_sync_at' => now()->subMinutes(15)]);
    }
}
