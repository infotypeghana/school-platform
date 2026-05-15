<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One feeding fee record per student per term.
 * Tracks total due, total paid, and derived status.
 *
 * Status is recomputed automatically in the saving hook
 * (mirrors the pattern used by the Fee model).
 */
class FeedingFee extends Model
{
    use HasTenantScope, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'school_class_id',
        'term_id',
        'billing_mode',
        'rate_per_day',
        'feeding_days',
        'amount_due',
        'amount_paid',
        'status',
        'is_exempt',
        'exemption_reason',
        'notes',
    ];

    protected $casts = [
        'rate_per_day' => 'float',
        'amount_due'   => 'float',
        'amount_paid'  => 'float',
        'is_exempt'    => 'boolean',
        'feeding_days' => 'integer',
    ];

    // ── Auto-compute status ───────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (FeedingFee $fee) {
            if ($fee->is_exempt) {
                $fee->status = 'exempt';
                return;
            }

            $balance = $fee->amount_due - $fee->amount_paid;

            if ($balance <= 0) {
                $fee->status = 'paid';
            } elseif ($fee->amount_paid > 0) {
                $fee->status = 'partial';
            } else {
                $fee->status = 'unpaid';
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeedingPayment::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function balance(): float
    {
        return max(0, $this->amount_due - $this->amount_paid);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'paid'    => 'bg-green-100 text-green-800',
            'partial' => 'bg-amber-100 text-amber-800',
            'exempt'  => 'bg-purple-100 text-purple-800',
            default   => 'bg-red-100 text-red-800',
        };
    }
}
