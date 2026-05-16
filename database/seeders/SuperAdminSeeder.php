<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Production-safe seeder — creates ONLY the super admin account.
 * Does not touch demo schools, demo data, or academic calendar.
 *
 * Usage:
 *   php artisan db:seed --class=SuperAdminSeeder
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@schoolms.com.gh');
        $pass  = env('SUPER_ADMIN_PASSWORD');

        if (! $pass) {
            $this->command->error('SUPER_ADMIN_PASSWORD is not set in .env — aborting super admin creation.');
            $this->command->warn('Set SUPER_ADMIN_PASSWORD in your .env and re-run.');
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'      => 'Super Administrator',
                'password'  => Hash::make($pass),
                'role'      => 'super_admin',
                'tenant_id' => null,
            ]
        );

        if ($user->wasRecentlyCreated) {
            $this->command->info("Super admin created: {$email}");
        } else {
            $this->command->info("Super admin already exists: {$email}");
        }
    }
}
