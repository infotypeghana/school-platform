<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// SubscriptionPackage resolved via belongsTo — no explicit import needed (same namespace)

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id', 'academic_year_id', 'term_id', 'plan_id',
        'package_id', 'student_count', 'price_per_student',
        'amount', 'start_date', 'end_date', 'grace_ends_at',
        'status', 'is_trial', 'activated_at', 'locked_at',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'end_date'         => 'date',
        'grace_ends_at'    => 'datetime',
        'activated_at'     => 'datetime',
        'locked_at'        => 'datetime',
        'is_trial'         => 'boolean',
        'student_count'    => 'integer',
        'price_per_student'=> 'decimal:2',
    ];

    // Status constants
    const STATUS_TRIAL   = 'trial';
    const STATUS_ACTIVE  = 'active';
    const STATUS_GRACE   = 'grace';
    const STATUS_LOCKED  = 'locked';
    const STATUS_SUSPENDED = 'suspended';

    /** @return BelongsTo<Tenant, Subscription> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<SubscriptionPackage, Subscription> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }

    /** @return BelongsTo<AcademicTerm, Subscription> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(SubscriptionNotification::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isAccessible(): bool
    {
        return in_array($this->status, [self::STATUS_TRIAL, self::STATUS_ACTIVE, self::STATUS_GRACE]);
    }

    public function isGrace(): bool
    {
        return $this->status === self::STATUS_GRACE;
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_LOCKED, self::STATUS_SUSPENDED]);
    }

    public function graceDaysRemaining(): int
    {
        if (! $this->grace_ends_at) {
            return 0;
        }
        return max(0, (int) Carbon::today()->diffInDays($this->grace_ends_at, false));
    }

    public function graceIsUrgent(): bool
    {
        return $this->graceDaysRemaining() < 3;
    }

    public function notificationSent(string $type): bool
    {
        return $this->notifications()->where('type', $type)->exists();
    }

    public function transitionToGrace(): void
    {
        $graceDays = (int) config('billing.grace_period_days', 5);
        $this->update([
            'status'        => self::STATUS_GRACE,
            'grace_ends_at' => now()->addDays($graceDays),
        ]);
        $this->tenant?->update(['status' => self::STATUS_GRACE]);
    }

    public function transitionToLocked(): void
    {
        $this->update([
            'status'    => self::STATUS_LOCKED,
            'locked_at' => now(),
        ]);
        $this->tenant?->update(['status' => self::STATUS_LOCKED]);
    }

    public function transitionToActive(): void
    {
        $this->update([
            'status'       => self::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);
        $this->tenant?->update(['status' => self::STATUS_ACTIVE]);
    }
}
