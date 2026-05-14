<?php

namespace App\Services;

/**
 * Ghana Basic Education Grading System
 *
 * Aligned with GES/WAEC BECE grading scale.
 * Total score = CA (max 30) + Exam (max 70) = 100
 *
 * Grade   Score Range   Remark
 * ─────────────────────────────
 * A1      80 – 100      Excellent
 * B2      70 – 79       Very Good
 * B3      60 – 69       Good
 * C4      55 – 59       Credit
 * C5      50 – 54       Credit
 * C6      45 – 49       Credit
 * D7      40 – 44       Pass
 * E8      35 – 39       Pass
 * F9       0 – 34       Fail
 */
class GradeCalculator
{
    /**
     * Grade boundaries: [min, max, grade, remark, points].
     */
    private static array $scale = [
        ['min' => 80, 'max' => 100, 'grade' => 'A1', 'remark' => 'Excellent',  'points' => 1],
        ['min' => 70, 'max' => 79,  'grade' => 'B2', 'remark' => 'Very Good',  'points' => 2],
        ['min' => 60, 'max' => 69,  'grade' => 'B3', 'remark' => 'Good',       'points' => 3],
        ['min' => 55, 'max' => 59,  'grade' => 'C4', 'remark' => 'Credit',     'points' => 4],
        ['min' => 50, 'max' => 54,  'grade' => 'C5', 'remark' => 'Credit',     'points' => 5],
        ['min' => 45, 'max' => 49,  'grade' => 'C6', 'remark' => 'Credit',     'points' => 6],
        ['min' => 40, 'max' => 44,  'grade' => 'D7', 'remark' => 'Pass',       'points' => 7],
        ['min' => 35, 'max' => 39,  'grade' => 'E8', 'remark' => 'Pass',       'points' => 8],
        ['min' => 0,  'max' => 34,  'grade' => 'F9', 'remark' => 'Fail',       'points' => 9],
    ];

    /**
     * Return the letter grade for a given total score.
     */
    public static function grade(float $score): string
    {
        // GES scores are integer-based; floor fractional scores (e.g. 79.9 → 79 = B2)
        $score = (int) max(0, min(100, floor($score)));

        foreach (self::$scale as $band) {
            if ($score >= $band['min'] && $score <= $band['max']) {
                return $band['grade'];
            }
        }

        return 'F9';
    }

    /**
     * Return the remark for a given total score or grade.
     */
    public static function remark(string $grade): string
    {
        foreach (self::$scale as $band) {
            if ($band['grade'] === $grade) {
                return $band['remark'];
            }
        }
        return 'Fail';
    }

    /**
     * Return the numeric points for a grade (lower = better, like golf).
     * Used for aggregate scoring in BECE-style total.
     */
    public static function points(string $grade): int
    {
        foreach (self::$scale as $band) {
            if ($band['grade'] === $grade) {
                return $band['points'];
            }
        }
        return 9;
    }

    /**
     * Full entry: score → [grade, remark, points].
     */
    public static function evaluate(float $score): array
    {
        $score = (int) max(0, min(100, floor($score)));

        foreach (self::$scale as $band) {
            if ($score >= $band['min'] && $score <= $band['max']) {
                return [
                    'grade'  => $band['grade'],
                    'remark' => $band['remark'],
                    'points' => $band['points'],
                ];
            }
        }

        return ['grade' => 'F9', 'remark' => 'Fail', 'points' => 9];
    }

    /**
     * Validate CA score: must be 0–30.
     */
    public static function validateCA(float $score): bool
    {
        return $score >= 0 && $score <= 30;
    }

    /**
     * Validate Exam score: must be 0–70.
     */
    public static function validateExam(float $score): bool
    {
        return $score >= 0 && $score <= 70;
    }

    /**
     * Compute class positions from an array of [student_id => total_score].
     * Returns [student_id => position].
     */
    public static function computePositions(array $scores): array
    {
        arsort($scores); // highest first

        $positions  = [];
        $rank       = 1;
        $prevScore  = null;
        $skipped    = 0;

        foreach ($scores as $studentId => $score) {
            if ($score !== $prevScore) {
                $rank     += $skipped;
                $skipped   = 0;
            }
            $positions[$studentId] = $rank;
            $prevScore = $score;
            $skipped++;
        }

        return $positions;
    }

    /**
     * Aggregate score for best-of-N subjects (BECE style).
     * Lower aggregate = better performance.
     */
    public static function aggregate(array $grades, int $bestOf = 6): int
    {
        $points = array_map(fn ($g) => self::points($g), $grades);
        sort($points); // ascending — take the lowest (best)
        return array_sum(array_slice($points, 0, $bestOf));
    }

    /**
     * Return the full grading scale (for display/legend in report cards).
     */
    public static function scale(): array
    {
        return self::$scale;
    }

    /**
     * Determine if a student passed (C6 or better = points <= 6).
     */
    public static function isPassing(string $grade): bool
    {
        return self::points($grade) <= 6;
    }
}
