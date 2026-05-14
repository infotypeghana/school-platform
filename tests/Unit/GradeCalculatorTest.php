<?php

namespace Tests\Unit;

use App\Services\GradeCalculator;
use Tests\TestCase;

class GradeCalculatorTest extends TestCase
{
    // ── grade() ──────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('gradeProvider')]
    public function test_grade_boundaries(float $score, string $expectedGrade): void
    {
        $this->assertSame($expectedGrade, GradeCalculator::grade($score));
    }

    public static function gradeProvider(): array
    {
        return [
            'A1 at 100'        => [100,  'A1'],
            'A1 at 80'         => [80,   'A1'],
            'B2 at 79'         => [79,   'B2'],
            'B2 at 70'         => [70,   'B2'],
            'B3 at 69'         => [69,   'B3'],
            'B3 at 60'         => [60,   'B3'],
            'C4 at 59'         => [59,   'C4'],
            'C4 at 55'         => [55,   'C4'],
            'C5 at 54'         => [54,   'C5'],
            'C5 at 50'         => [50,   'C5'],
            'C6 at 49'         => [49,   'C6'],
            'C6 at 45'         => [45,   'C6'],
            'D7 at 44'         => [44,   'D7'],
            'D7 at 40'         => [40,   'D7'],
            'E8 at 39'         => [39,   'E8'],
            'E8 at 35'         => [35,   'E8'],
            'F9 at 34'         => [34,   'F9'],
            'F9 at 0'          => [0,    'F9'],
            'F9 at 1'          => [1,    'F9'],
            'Above 100 clamps' => [150,  'A1'],  // clamped to 100 → A1
            'Below 0 clamps'   => [-5,   'F9'],  // clamped to 0 → F9
            'Decimal 79.9 floors to 79 (B2)' => [79.9, 'B2'],
            'Decimal 80.0 floors to 80 (A1)' => [80.0, 'A1'],
            'Decimal 59.7 floors to 59 (C4)' => [59.7, 'C4'],
        ];
    }

    // ── remark() ─────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('remarkProvider')]
    public function test_remark_for_grade(string $grade, string $expectedRemark): void
    {
        $this->assertSame($expectedRemark, GradeCalculator::remark($grade));
    }

    public static function remarkProvider(): array
    {
        return [
            ['A1', 'Excellent'],
            ['B2', 'Very Good'],
            ['B3', 'Good'],
            ['C4', 'Credit'],
            ['C5', 'Credit'],
            ['C6', 'Credit'],
            ['D7', 'Pass'],
            ['E8', 'Pass'],
            ['F9', 'Fail'],
        ];
    }

    public function test_remark_for_unknown_grade_returns_fail(): void
    {
        $this->assertSame('Fail', GradeCalculator::remark('X99'));
    }

    // ── points() ─────────────────────────────────────────────────────────────

    public function test_points_are_sequential(): void
    {
        $this->assertSame(1, GradeCalculator::points('A1'));
        $this->assertSame(2, GradeCalculator::points('B2'));
        $this->assertSame(9, GradeCalculator::points('F9'));
    }

    public function test_unknown_grade_points_returns_9(): void
    {
        $this->assertSame(9, GradeCalculator::points('Z1'));
    }

    // ── evaluate() ───────────────────────────────────────────────────────────

    public function test_evaluate_returns_all_fields(): void
    {
        $result = GradeCalculator::evaluate(85);

        $this->assertSame('A1',        $result['grade']);
        $this->assertSame('Excellent', $result['remark']);
        $this->assertSame(1,           $result['points']);
    }

    public function test_evaluate_boundary_55(): void
    {
        $result = GradeCalculator::evaluate(55);
        $this->assertSame('C4', $result['grade']);
        $this->assertSame('Credit', $result['remark']);
    }

    // ── isPassing() ──────────────────────────────────────────────────────────

    public function test_c6_and_above_are_passing(): void
    {
        foreach (['A1', 'B2', 'B3', 'C4', 'C5', 'C6'] as $grade) {
            $this->assertTrue(GradeCalculator::isPassing($grade), "{$grade} should be passing");
        }
    }

    public function test_d7_and_below_are_not_passing(): void
    {
        foreach (['D7', 'E8', 'F9'] as $grade) {
            $this->assertFalse(GradeCalculator::isPassing($grade), "{$grade} should not be passing");
        }
    }

    // ── validateCA() / validateExam() ────────────────────────────────────────

    public function test_ca_validation(): void
    {
        $this->assertTrue(GradeCalculator::validateCA(0));
        $this->assertTrue(GradeCalculator::validateCA(15));
        $this->assertTrue(GradeCalculator::validateCA(30));
        $this->assertFalse(GradeCalculator::validateCA(31));
        $this->assertFalse(GradeCalculator::validateCA(-1));
    }

    public function test_exam_validation(): void
    {
        $this->assertTrue(GradeCalculator::validateExam(0));
        $this->assertTrue(GradeCalculator::validateExam(35));
        $this->assertTrue(GradeCalculator::validateExam(70));
        $this->assertFalse(GradeCalculator::validateExam(71));
        $this->assertFalse(GradeCalculator::validateExam(-0.1));
    }

    // ── computePositions() ───────────────────────────────────────────────────

    public function test_positions_are_assigned_correctly(): void
    {
        $scores    = [1 => 90, 2 => 85, 3 => 85, 4 => 70];
        $positions = GradeCalculator::computePositions($scores);

        $this->assertSame(1, $positions[1]); // highest
        $this->assertSame(2, $positions[2]); // tied 2nd
        $this->assertSame(2, $positions[3]); // tied 2nd
        $this->assertSame(4, $positions[4]); // skipped 3rd due to tie
    }

    public function test_positions_with_all_equal_scores(): void
    {
        $scores    = [1 => 75, 2 => 75, 3 => 75];
        $positions = GradeCalculator::computePositions($scores);

        foreach ($positions as $pos) {
            $this->assertSame(1, $pos);
        }
    }

    // ── aggregate() ──────────────────────────────────────────────────────────

    public function test_aggregate_takes_best_6(): void
    {
        // 7 subjects: 5 A1s (1pt), 1 B2 (2pt), 1 F9 (9pt)
        $grades    = ['A1', 'A1', 'A1', 'A1', 'A1', 'B2', 'F9'];
        $aggregate = GradeCalculator::aggregate($grades, 6);

        // best 6: 5×1 + 1×2 = 7
        $this->assertSame(7, $aggregate);
    }

    public function test_aggregate_perfect_6_subjects(): void
    {
        $grades    = ['A1', 'A1', 'A1', 'A1', 'A1', 'A1'];
        $aggregate = GradeCalculator::aggregate($grades, 6);
        $this->assertSame(6, $aggregate); // 6×1 = 6 (best possible)
    }

    public function test_aggregate_worst_case(): void
    {
        $grades    = ['F9', 'F9', 'F9', 'F9', 'F9', 'F9'];
        $aggregate = GradeCalculator::aggregate($grades, 6);
        $this->assertSame(54, $aggregate); // 6×9 = 54
    }
}
