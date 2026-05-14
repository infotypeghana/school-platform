<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Super Admin ───────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'superadmin@schoolms.gh'],
            [
                'name'      => 'Super Administrator',
                'password'  => Hash::make('SuperAdmin@123'),
                'role'      => 'super_admin',
                'tenant_id' => null,
            ]
        );

        $this->command->info('✅ Super admin created: superadmin@schoolms.gh / SuperAdmin@123');

        // ── School Admin accounts (one per demo tenant) ───────────────────
        $schools = [
            ['slug' => 'accra-academy',      'email' => 'admin@accraacademy.edu.gh',  'name' => 'Accra Academy Admin',      'pass' => 'AccraAdmin@123'],
            ['slug' => 'kumasi-academy',     'email' => 'admin@kumasiacademy.edu.gh', 'name' => 'Kumasi Academy Admin',     'pass' => 'KumasiAdmin@123'],
            ['slug' => 'cape-coast-academy', 'email' => 'admin@cca.edu.gh',           'name' => 'Cape Coast Academy Admin', 'pass' => 'CapeCoastAdmin@123'],
        ];

        foreach ($schools as $school) {
            $tenant = Tenant::where('slug', $school['slug'])->first();

            if (! $tenant) {
                $this->command->warn("⚠ Tenant [{$school['slug']}] not found — run DemoTenantSeeder first.");
                continue;
            }

            User::firstOrCreate(
                ['email' => $school['email']],
                [
                    'name'      => $school['name'],
                    'password'  => Hash::make($school['pass']),
                    'role'      => 'school_admin',
                    'tenant_id' => $tenant->id,
                ]
            );

            $this->command->info("✅ School admin: {$school['email']} / {$school['pass']}");
        }
    }
}
