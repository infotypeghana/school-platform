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
 *
 * Per-tenant overrides are stored in tenants.grading_settings (JSON).
 * When null the platform falls back to the built-in GES standard scale.
 */
class GradeCalculator
{
    /** Default CA weight under the GES standard. */
    public const DEFAULT_CA_MAX   = 30;

    /** Default Exam weight under the GES standard. */
    public const DEFAULT_EXAM_MAX = 70;

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

    // ── Per-tenant helpers ────────────────────────────────────────────────────

    /**
     * Return the resolved grading settings for a tenant.
     *
     * Falls back to GES defaults when the tenant has no override.
     *
     * @param  object|null  $tenant  Tenant model instance (or null → defaults)
     * @return array{ca_max: int, exam_max: int, scale: array}
     */
    public static function tenantSettings(?object $tenant): array
    {
        $stored = $tenant?->grading_settings ?? null;

        if (
            is_array($stored)
            && isset($stored['ca_max'], $stored['exam_max'], $stored['scale'])
            && is_array($stored['scale'])
            && count($stored['scale']) > 0
        ) {
            return [
                'ca_max'   => (int) $stored['ca_max'],
                'exam_max' => (int) $stored['exam_max'],
                'scale'    => $stored['scale'],
            ];
        }

        return [
            'ca_max'   => self::DEFAULT_CA_MAX,
            'exam_max' => self::DEFAULT_EXAM_MAX,
            'scale'    => self::$scale,
        ];
    }

    /**
     * Return the grading scale for a tenant (falls back to GES defaults).
     *
     * @param  object|null  $tenant
     * @return array<int, array{min: int, max: int, grade: string, remark: string, points: int}>
     */
    public static function tenantScale(?object $tenant): array
    {
        return self::tenantSettings($tenant)['scale'];
    }

    /**
     * Return the CA maximum for a tenant (default 30).
     */
    public static function tenantCaMax(?object $tenant): int
    {
        return self::tenantSettings($tenant)['ca_max'];
    }

    /**
     * Return the Exam maximum for a tenant (default 70).
     */
    public static function tenantExamMax(?object $tenant): int
    {
        return self::tenantSettings($tenant)['exam_max'];
    }

    /**
     * Return the letter grade for a given total score using the tenant's scale.
     *
     * When $scale is null the built-in GES scale is used (backward-compatible).
     *
     * @param  float       $score
     * @param  array|null  $scale  Band array from tenantScale() / scale()
     */
    public static function gradeWithScale(float $score, ?array $scale = null): string
    {
        $bands = $scale ?? self::$scale;
        $score = (int) max(0, min(100, floor($score)));

        foreach ($bands as $band) {
            if ($score >= (int) $band['min'] && $score <= (int) $band['max']) {
                return (string) $band['grade'];
            }
        }

        return 'F9';
    }

    /**
     * Evaluate a score using the tenant's scale and return [grade, remark, points].
     *
     * @param  float       $score
     * @param  array|null  $scale  Band array from tenantScale() / scale()
     * @return array{grade: string, remark: string, points: int}
     */
    public static function evaluateWithScale(float $score, ?array $scale = null): array
    {
        $bands = $scale ?? self::$scale;
        $score = (int) max(0, min(100, floor($score)));

        foreach ($bands as $band) {
            if ($score >= (int) $band['min'] && $score <= (int) $band['max']) {
                return [
                    'grade'  => (string) $band['grade'],
                    'remark' => (string) $band['remark'],
                    'points' => (int)    $band['points'],
                ];
            }
        }

        return ['grade' => 'F9', 'remark' => 'Fail', 'points' => 9];
    }
}
