<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $currentYear = AcademicYear::where('is_current', true)->first();

        if (! $currentTerm || ! $currentYear) {
            $this->command->error('Run AcademicCalendarSeeder first.');
            return;
        }

        $schools = [
            [
                'name'          => 'Accra Academy School',
                'slug'          => 'accra-academy',
                'email'         => 'admin@accraacademy.edu.gh',
                'phone'         => '030-202-0000',
                'address'       => 'Bubuashie, Accra, Ghana',
                'contact_phone' => '024-000-0001',
                'status'        => 'active',
                'sub_status'    => Subscription::STATUS_ACTIVE,
            ],
            [
                'name'          => 'Kumasi Academy',
                'slug'          => 'kumasi-academy',
                'email'         => 'admin@kumasiacademy.edu.gh',
                'phone'         => '032-202-0000',
                'address'       => 'Kumasi, Ashanti Region, Ghana',
                'contact_phone' => '024-000-0002',
                'status'        => 'trial',
                'sub_status'    => Subscription::STATUS_TRIAL,
            ],
            [
                'name'          => 'Cape Coast Academy',
                'slug'          => 'cape-coast-academy',
                'email'         => 'admin@cca.edu.gh',
                'phone'         => '033-202-0000',
                'address'       => 'Cape Coast, Central Region, Ghana',
                'contact_phone' => '024-000-0003',
                'status'        => 'grace',
                'sub_status'    => Subscription::STATUS_GRACE,
            ],
        ];

        foreach ($schools as $school) {
            $subStatus = $school['sub_status'];
            unset($school['sub_status']);

            $tenant = Tenant::firstOrCreate(
                ['slug' => $school['slug']],
                [...$school, 'uuid' => Str::uuid()]
            );

            // Create subscription
            $graceEndsAt = $subStatus === Subscription::STATUS_GRACE
                ? Carbon::now()->addDays(3)
                : null;

            $lockedAt = $subStatus === Subscription::STATUS_LOCKED
                ? now()
                : null;

            Subscription::firstOrCreate(
                ['tenant_id' => $tenant->id, 'term_id' => $currentTerm->id],
                [
                    'academic_year_id' => $currentYear->id,
                    'plan_id'          => 'standard',
                    'amount'           => 850.00,
                    'start_date'       => $currentTerm->start_date,
                    'end_date'         => $currentTerm->end_date,
                    'grace_ends_at'    => $graceEndsAt,
                    'status'           => $subStatus,
                    'is_trial'         => $subStatus === Subscription::STATUS_TRIAL,
                    'activated_at'     => now(),
                    'locked_at'        => $lockedAt,
                ]
            );
        }

        $this->command->info('✅ Demo tenants seeded: ' . count($schools) . ' schools.');
    }
}
