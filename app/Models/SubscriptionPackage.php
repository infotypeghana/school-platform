<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPackage extends Model
{
    protected $fillable = [
        'name', 'slug', 'description',
        'price_per_student', 'min_students',
        'billing_cycle', 'features',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'price_per_student' => 'decimal:2',
        'min_students'      => 'integer',
        'sort_order'        => 'integer',
        'is_active'         => 'boolean',
        'features'          => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'package_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** @return \Illuminate\Database\Eloquent\Collection<int, SubscriptionPackage> */
    public static function active(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)->orderBy('sort_order')->get();
    }

    // ── Business logic ────────────────────────────────────────────────────────

    /**
     * Calculate the invoice amount for a given student count.
     * Enforces the minimum-students floor.
     */
    public function calculateAmount(int $studentCount): float
    {
        $billable = max($studentCount, $this->min_students);
        return round($billable * (float) $this->price_per_student, 2);
    }

    /**
     * Human-readable price label, e.g. "GHS 8.00 / student / term"
     */
    public function priceLabel(): string
    {
        $cycle = $this->billing_cycle === 'annual' ? 'year' : 'term';
        $currency = config('billing.currency', 'GHS');
        return "{$currency} " . number_format((float) $this->price_per_student, 2) . ' / student / ' . $cycle;
    }

    public function billingCycleLabel(): string
    {
        return match ($this->billing_cycle) {
            'annual' => 'Annual',
            default  => 'Per Term',
        };
    }
}
