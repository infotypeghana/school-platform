<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCard extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id', 'student_id', 'school_class_id', 'term_id',
        'total_subjects',
        'overall_position',    // e.g. 3
        'out_of',              // e.g. 42 (students in class)
        'class_teacher_remark',
        'headmaster_remark',
        'attendance_present',
        'attendance_total',
        'generated_at',
        'pdf_path',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

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

    public function getAttendancePercentageAttribute(): float
    {
        if (! $this->attendance_total) {
            return 0.0;
        }
        return round(($this->attendance_present / $this->attendance_total) * 100, 1);
    }
}
