<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AcademicCalendarSeeder::class,  // years + terms (must be first)
            DemoTenantSeeder::class,        // 3 demo schools + subscriptions
            UserSeeder::class,              // super admin + school admin accounts
        ]);
    }
}
