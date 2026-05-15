<?php

namespace App\Models;

use App\Services\GradeCalculator;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'student_id', 'subject_id', 'school_class_id', 'term_id',
        'ca_score',        // max is tenant-configurable (default GES: 30)
        'exam_score',      // max is tenant-configurable (default GES: 70)
        'total_score',     // computed: ca + exam
        'grade',           // A1–F9 (or tenant-defined grade)
        'position_in_class',
        'class_average',
        'highest_score',
        'lowest_score',
    ];

    protected $casts = [
        'ca_score'         => 'float',
        'exam_score'       => 'float',
        'total_score'      => 'float',
        'class_average'    => 'float',
        'highest_score'    => 'float',
        'lowest_score'     => 'float',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    /**
     * Compute total and grade before saving using the tenant's grading scale.
     */
    protected static function booted(): void
    {
        static::saving(function (Assessment $assessment) {
            $assessment->total_score = round(($assessment->ca_score ?? 0) + ($assessment->exam_score ?? 0), 2);

            // Resolve the tenant so we can apply per-tenant grading scale.
            // During web requests / jobs, currentTenant is bound in the container.
            $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
            $scale  = GradeCalculator::tenantScale($tenant);

            $assessment->grade = GradeCalculator::gradeWithScale($assessment->total_score, $scale);
        });
    }

    public function getRemarkAttribute(): string
    {
        // grade is null for unsaved/incomplete assessments — avoid returning "Fail" misleadingly.
        return $this->grade !== null ? GradeCalculator::remark($this->grade) : '';
    }
}
