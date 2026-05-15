<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feeding fee rate configuration.
 *
 * One row = a rate for the whole school (school_class_id = null)
 * or a per-class override (school_class_id = X).
 *
 * The service layer picks the most specific match.
 */
class FeedingConfig extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'school_class_id',
        'rate_per_day',
        'billing_mode',
        'school_days_per_week',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'rate_per_day'        => 'float',
        'school_days_per_week'=> 'integer',
        'is_active'           => 'boolean',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** Human-readable billing mode label. */
    public function billingModeLabel(): string
    {
        return match ($this->billing_mode) {
            'daily'   => 'Daily',
            'weekly'  => 'Weekly',
            'monthly' => 'Monthly',
            'termly'  => 'Termly (full term)',
            default   => ucfirst($this->billing_mode),
        };
    }
}
