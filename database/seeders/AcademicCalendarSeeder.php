<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicCalendarSeeder extends Seeder
{
    public function run(): void
    {
        // Use firstOrCreate — safe to run multiple times without FK violations
        $years = [
            ['year_label' => '2023/2024', 'is_current' => false],
            ['year_label' => '2024/2025', 'is_current' => true],
            ['year_label' => '2025/2026', 'is_current' => false],
        ];

        $terms = [
            // 2023/2024
            '2023/2024' => [
                [1, 'First Term',  '2023-09-04', '2023-12-15', false],
                [2, 'Second Term', '2024-01-08', '2024-04-05', false],
                [3, 'Third Term',  '2024-04-29', '2024-08-02', false],
            ],
            // 2024/2025 (current year)
            '2024/2025' => [
                [1, 'First Term',  '2024-09-02', '2024-12-13', false],
                [2, 'Second Term', '2025-01-06', '2025-04-04', false],
                [3, 'Third Term',  '2025-04-28', '2025-08-01', true], // ← current
            ],
            // 2025/2026 (upcoming)
            '2025/2026' => [
                [1, 'First Term',  '2025-09-01', '2025-12-12', false],
                [2, 'Second Term', '2026-01-05', '2026-04-03', false],
                [3, 'Third Term',  '2026-04-27', '2026-07-31', false],
            ],
        ];

        foreach ($years as $yearData) {
            $year = AcademicYear::firstOrCreate(
                ['year_label' => $yearData['year_label']],
                ['is_current' => $yearData['is_current']]
            );

            foreach ($terms[$yearData['year_label']] as [$number, $name, $start, $end, $isCurrent]) {
                AcademicTerm::firstOrCreate(
                    ['academic_year_id' => $year->id, 'term_number' => $number],
                    [
                        'term_name'  => $name,
                        'start_date' => $start,
                        'end_date'   => $end,
                        'is_current' => $isCurrent,
                    ]
                );
            }
        }

        $this->command->info('✅ Academic calendar seeded: 3 years, 9 terms.');
    }
}
