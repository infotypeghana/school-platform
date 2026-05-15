<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    use HasFactory;
    protected $fillable = [
        'academic_year_id', 'term_number', 'term_name',
        'start_date', 'end_date', 'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_current' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'term_id');
    }

    /**
     * Resolve the current term.
     *
     * When a tenant is bound in the service container (i.e. inside the school
     * admin portal), prefer the tenant's own chosen term over the global flag.
     * This lets each school set their own "active term" independently of one
     * another and independently of the super-admin's global `is_current` flag.
     */
    public static function current(): ?self
    {
        if (app()->bound('currentTenant')) {
            /** @var \App\Models\Tenant $tenant */
            $tenant = app('currentTenant');
            if ($tenant->current_term_id) {
                return static::find($tenant->current_term_id);
            }
        }
        return static::where('is_current', true)->first();
    }

    public function graceEndsAt(): Carbon
    {
        return $this->end_date->addDays((int) config('billing.grace_period_days', 5));
    }

    public function isExpired(): bool
    {
        return Carbon::today()->isAfter($this->end_date);
    }
}
