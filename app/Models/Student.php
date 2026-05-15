<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'school_class_id', 'admission_number',
        'first_name', 'last_name', 'date_of_birth', 'gender',
        'photo', 'guardian_name', 'guardian_phone', 'guardian_email',
        'address', 'status', 'admission_date',
    ];

    protected $casts = [
        'date_of_birth'  => 'date',
        'admission_date' => 'date',
    ];

    // ── Auto-generate admission number on creation ────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Student $student): void {
            if (empty($student->admission_number)) {
                $student->admission_number = static::generateAdmissionNumber(
                    (int) $student->tenant_id
                );
            }
        });
    }

    private static function generateAdmissionNumber(int $tenantId): string
    {
        $year = now()->year;

        // Count existing students for this tenant in the current year
        // (withoutTenantScope so it works during tests without a bound tenant)
        $sequence = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('ADM-%d-%04d', $year, $sequence);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class);
    }

    public function feedingFees(): HasMany
    {
        return $this->hasMany(FeedingFee::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class)->orderBy('created_at');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
