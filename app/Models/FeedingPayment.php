<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable payment record. Never soft-deleted — every payment is permanent audit.
 * To reverse, record a credit/refund note in the admin UI (future feature).
 */
class FeedingPayment extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'feeding_fee_id',
        'student_id',
        'term_id',
        'amount',
        'payment_date',
        'receipt_number',
        'payment_method',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'amount'       => 'float',
        'payment_date' => 'date',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function feedingFee(): BelongsTo
    {
        return $this->belongsTo(FeedingFee::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function methodLabel(): string
    {
        return match ($this->payment_method) {
            'cash'          => 'Cash',
            'mobile_money'  => 'Mobile Money',
            'bank_transfer' => 'Bank Transfer',
            'cheque'        => 'Cheque',
            default         => ucfirst($this->payment_method),
        };
    }
}
