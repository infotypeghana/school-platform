<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchemeOfWork extends Model
{
    use HasTenantScope, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'teacher_id', 'school_class_id', 'subject_id', 'term_id',
        'title', 'description', 'status',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(SchemeOfWorkWeek::class)->orderBy('week_number');
    }

    public function completedWeeks(): int
    {
        return $this->weeks()->where('is_completed', true)->count();
    }
}
