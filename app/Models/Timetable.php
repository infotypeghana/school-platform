<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timetable extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id', 'school_class_id', 'subject_id', 'teacher_id',
        'day_of_week', 'period_number', 'start_time', 'end_time', 'label',
    ];

    protected $casts = [
        'day_of_week'   => 'integer',
        'period_number' => 'integer',
    ];

    // ── Constants ─────────────────────────────────────────────────────────────

    public const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
    ];

    public const DAY_SHORT = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getDayNameAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? '';
    }

    public function getTimeRangeAttribute(): string
    {
        return substr($this->start_time, 0, 5) . '–' . substr($this->end_time, 0, 5);
    }
}
