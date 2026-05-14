<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admission extends Model
{
    use HasTenantScope, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'term_id', 'student_id',
        'first_name', 'last_name', 'date_of_birth', 'gender',
        'previous_school', 'class_applying_for',
        'guardian_name', 'guardian_phone', 'guardian_email',
        'address', 'status',   // pending | accepted | rejected | enrolled
        'notes',
        'submitted_at', 'enrolled_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'submitted_at'  => 'datetime',
        'enrolled_at'   => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isEnrolled(): bool
    {
        return $this->student_id !== null;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
