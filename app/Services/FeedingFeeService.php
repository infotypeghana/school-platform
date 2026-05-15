<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\FeedingConfig;
use App\Models\FeedingFee;
use App\Models\FeedingPayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * All business logic for the Feeding Fee module.
 *
 * ┌──────────────────────────────────────────────────────────────────┐
 * │  CONFIG RESOLUTION (effectiveConfig)                             │
 * │  1. Look for a per-class config for school_class_id              │
 * │  2. Fall back to the school-wide config (class_id = null)        │
 * │  3. Return null if nothing is configured                         │
 * └──────────────────────────────────────────────────────────────────┘
 */
class FeedingFeeService
{
    // ── Config resolution ─────────────────────────────────────────────────────

    /**
     * Return the most specific active config for a class.
     * Per-class takes precedence over school-wide.
     */
    public function effectiveConfig(int $classId): ?FeedingConfig
    {
        // Per-class config first
        $config = FeedingConfig::where('school_class_id', $classId)
            ->where('is_active', true)
            ->first();

        if ($config) {
            return $config;
        }

        // Fall back to school-wide default
        return FeedingConfig::whereNull('school_class_id')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Calculate the feeding amount due for a given config and number of days.
     * Billing mode just determines how the user inputs feeding_days; the
     * math is always: rate_per_day × feeding_days.
     */
    public function calculateDue(FeedingConfig $config, int $feedingDays): float
    {
        return round($config->rate_per_day * $feedingDays, 2);
    }

    /**
     * Derive sensible default feeding_days from billing mode.
     */
    public function defaultFeedingDays(FeedingConfig $config, int $termWeeks = 13): int
    {
        $daysPerWeek = $config->school_days_per_week;

        return match ($config->billing_mode) {
            'daily'   => $termWeeks * $daysPerWeek,
            'weekly'  => $termWeeks * $daysPerWeek,
            'monthly' => intval(round($termWeeks / 4.33)) * $daysPerWeek,
            'termly'  => $termWeeks * $daysPerWeek,
            default   => $termWeeks * $daysPerWeek,
        };
    }

    // ── Bulk assignment ───────────────────────────────────────────────────────

    /**
     * Assign (or skip existing) feeding fees for all active students in a class
     * for the given term.
     *
     * @return array{assigned: int, skipped: int}
     */
    public function assignToClass(SchoolClass $class, AcademicTerm $term, int $feedingDays): array
    {
        $config = $this->effectiveConfig($class->id);

        if (! $config) {
            throw new \RuntimeException('No feeding fee configuration found for this class or school.');
        }

        $amountDue = $this->calculateDue($config, $feedingDays);
        $students  = Student::where('school_class_id', $class->id)
            ->where('status', 'active')
            ->get();

        $assigned = 0;
        $skipped  = 0;

        foreach ($students as $student) {
            $exists = FeedingFee::where('student_id', $student->id)
                ->where('term_id', $term->id)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            FeedingFee::create([
                'student_id'      => $student->id,
                'school_class_id' => $class->id,
                'term_id'         => $term->id,
                'billing_mode'    => $config->billing_mode,
                'rate_per_day'    => $config->rate_per_day,
                'feeding_days'    => $feedingDays,
                'amount_due'      => $amountDue,
                'amount_paid'     => 0,
            ]);

            $assigned++;
        }

        return compact('assigned', 'skipped');
    }

    // ── Payment recording ─────────────────────────────────────────────────────

    /**
     * Record a payment against a feeding fee.
     * Updates amount_paid on the fee; status computed by model hook.
     *
     * @throws \RuntimeException if amount exceeds balance
     */
    public function recordPayment(
        FeedingFee $fee,
        float $amount,
        string $paymentDate,
        string $paymentMethod = 'cash',
        ?int $recordedBy = null,
        ?string $notes = null
    ): FeedingPayment {
        if ($fee->is_exempt) {
            throw new \RuntimeException('Cannot record payment on an exempt fee.');
        }

        $balance = $fee->balance();

        if ($amount > $balance + 0.005) {
            throw new \RuntimeException(
                "Amount (GHS {$amount}) exceeds outstanding balance (GHS {$balance})."
            );
        }

        return DB::transaction(function () use ($fee, $amount, $paymentDate, $paymentMethod, $recordedBy, $notes) {
            $payment = FeedingPayment::create([
                'feeding_fee_id' => $fee->id,
                'student_id'     => $fee->student_id,
                'term_id'        => $fee->term_id,
                'amount'         => $amount,
                'payment_date'   => $paymentDate,
                'receipt_number' => $this->generateReceiptNumber(),
                'payment_method' => $paymentMethod,
                'recorded_by'    => $recordedBy,
                'notes'          => $notes,
            ]);

            // Recalculate total paid from all payment records (safe with concurrency)
            $totalPaid = FeedingPayment::where('feeding_fee_id', $fee->id)->sum('amount');

            $fee->amount_paid = $totalPaid;
            $fee->save();

            return $payment;
        });
    }

    // ── Receipt number ────────────────────────────────────────────────────────

    /**
     * Generate a unique receipt number: FF-{SLUG}-{YY}-{NNNNN}
     * e.g. FF-ACA-26-00001
     */
    public function generateReceiptNumber(): string
    {
        $tenant = app('currentTenant');
        $slug   = strtoupper(substr($tenant?->slug ?? 'SCH', 0, 3));
        $year   = now()->format('y');

        // Find the last receipt for this tenant/year and increment
        $last = FeedingPayment::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant?->id)
            ->whereYear('created_at', now()->year)
            ->orderByDesc('id')
            ->value('receipt_number');

        $seq = 1;
        if ($last && preg_match('/(\d{5})$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('FF-%s-%s-%05d', $slug, $year, $seq);
    }

    // ── Statistics ────────────────────────────────────────────────────────────

    /**
     * Summary stats for a class in a term.
     *
     * @return array{total: int, paid: int, partial: int, unpaid: int, exempt: int, collected: float, outstanding: float}
     */
    public function classStats(int $classId, int $termId): array
    {
        $fees = FeedingFee::where('school_class_id', $classId)
            ->where('term_id', $termId)
            ->get();

        return [
            'total'       => $fees->count(),
            'paid'        => $fees->where('status', 'paid')->count(),
            'partial'     => $fees->where('status', 'partial')->count(),
            'unpaid'      => $fees->where('status', 'unpaid')->count(),
            'exempt'      => $fees->where('status', 'exempt')->count(),
            'collected'   => round($fees->sum('amount_paid'), 2),
            'outstanding' => round($fees->sum(fn ($f) => $f->balance()), 2),
        ];
    }

    /**
     * Tenant-wide stats for a term.
     *
     * @return array{total: int, paid: int, partial: int, unpaid: int, exempt: int, collected: float, outstanding: float, expected: float}
     */
    public function termStats(int $termId): array
    {
        $fees = FeedingFee::where('term_id', $termId)->get();

        return [
            'total'       => $fees->count(),
            'paid'        => $fees->where('status', 'paid')->count(),
            'partial'     => $fees->where('status', 'partial')->count(),
            'unpaid'      => $fees->where('status', 'unpaid')->count(),
            'exempt'      => $fees->where('status', 'exempt')->count(),
            'collected'   => round($fees->sum('amount_paid'), 2),
            'outstanding' => round($fees->sum(fn ($f) => $f->balance()), 2),
            'expected'    => round($fees->whereNotIn('status', ['exempt'])->sum('amount_due'), 2),
        ];
    }

    /**
     * Per-class summary rows for the report page.
     *
     * @return Collection<int, \stdClass>
     */
    public function classBreakdown(int $termId): Collection
    {
        return DB::table('feeding_fees')
            ->join('school_classes', 'school_classes.id', '=', 'feeding_fees.school_class_id')
            ->where('feeding_fees.term_id', $termId)
            ->whereNull('feeding_fees.deleted_at')
            ->select(
                'school_classes.id as class_id',
                'school_classes.name as class_name',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN feeding_fees.status = 'paid'    THEN 1 ELSE 0 END) as paid"),
                DB::raw("SUM(CASE WHEN feeding_fees.status = 'partial' THEN 1 ELSE 0 END) as partial"),
                DB::raw("SUM(CASE WHEN feeding_fees.status = 'unpaid'  THEN 1 ELSE 0 END) as unpaid"),
                DB::raw("SUM(CASE WHEN feeding_fees.status = 'exempt'  THEN 1 ELSE 0 END) as exempt"),
                DB::raw('SUM(feeding_fees.amount_due)  as expected'),
                DB::raw('SUM(feeding_fees.amount_paid) as collected'),
            )
            ->groupBy('school_classes.id', 'school_classes.name')
            ->orderBy('school_classes.name')
            ->get();
    }
}
