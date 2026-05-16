<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            // In production: only seed the super admin account.
            // Demo schools, demo data, and academic calendar are NOT seeded.
            // To force full seeding on production, use: php artisan db:seed --force
            $this->command->warn('Production environment detected — running SuperAdminSeeder only.');
            $this->command->warn('Set SUPER_ADMIN_PASSWORD in .env before running this seeder.');
            $this->call(SuperAdminSeeder::class);
            return;
        }

        // Local / staging: seed full demo dataset
        $this->call([
            AcademicCalendarSeeder::class,  // years + terms (must be first)
            DemoTenantSeeder::class,        // 3 demo schools + subscriptions
            UserSeeder::class,              // super admin + school admin accounts
        ]);
    }
}
