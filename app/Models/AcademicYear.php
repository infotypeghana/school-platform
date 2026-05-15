<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicYear extends Model
{
    use HasFactory;
    protected $fillable = ['year_label', 'is_current'];

    protected $casts = ['is_current' => 'boolean'];

    public function terms(): HasMany
    {
        return $this->hasMany(AcademicTerm::class);
    }

    /**
     * Resolve the current academic year.
     *
     * When a tenant is bound (school admin portal), derive the year from the
     * tenant's chosen current term so that each school's year follows their
     * own calendar setting, not the global `is_current` flag.
     */
    public static function current(): ?self
    {
        if (app()->bound('currentTenant')) {
            /** @var \App\Models\Tenant $tenant */
            $tenant = app('currentTenant');
            if ($tenant->current_term_id) {
                $term = AcademicTerm::with('academicYear')->find($tenant->current_term_id);
                return $term?->academicYear;
            }
        }
        return static::where('is_current', true)->first();
    }
}
