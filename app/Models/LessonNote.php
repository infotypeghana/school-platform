<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonNote extends Model
{
    use HasTenantScope, SoftDeletes;

    // ── GES Core Competencies ─────────────────────────────────────────────────
    const CORE_COMPETENCIES = [
        'cps' => 'Critical Thinking & Problem Solving',
        'ci'  => 'Creativity & Innovation',
        'cc'  => 'Communication & Collaboration',
        'cg'  => 'Cultural Identity & Global Citizenship',
        'pd'  => 'Personal Development & Leadership',
        'dl'  => 'Digital Literacy',
    ];

    const STATUS_DRAFT     = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED  = 'approved';
    const STATUS_REVISION  = 'revision_requested';

    protected $fillable = [
        'tenant_id', 'teacher_id', 'school_class_id', 'subject_id', 'term_id',
        'strand_id', 'sub_strand_id',
        'content_standard', 'indicator_code', 'indicator', 'performance_indicator',
        'title', 'type',
        'week_ending', 'lesson_date', 'day_of_week', 'period', 'duration',
        'reference_materials', 'tlr', 'core_competencies', 'keywords',
        'starter', 'main_activities', 'assessment', 'conclusion', 'homework',
        'status', 'submitted_at', 'approved_at', 'approved_by', 'revision_notes',
    ];

    protected $casts = [
        'week_ending'       => 'date',
        'lesson_date'       => 'date',
        'submitted_at'      => 'datetime',
        'approved_at'       => 'datetime',
        'core_competencies' => 'array',
        'duration'          => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

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

    public function strand(): BelongsTo
    {
        return $this->belongsTo(CurriculumStrand::class);
    }

    public function subStrand(): BelongsTo
    {
        return $this->belongsTo(CurriculumSubStrand::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LessonNoteAttachment::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'approved'           => 'bg-green-100 text-green-800',
            'submitted'          => 'bg-blue-100 text-blue-800',
            'revision_requested' => 'bg-amber-100 text-amber-800',
            default              => 'bg-gray-100 text-gray-600',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved'           => 'Approved',
            'submitted'          => 'Submitted',
            'revision_requested' => 'Revision Needed',
            default              => 'Draft',
        };
    }

    public function coreCompetencyLabels(): array
    {
        $selected = $this->core_competencies ?? [];
        return array_map(
            fn ($key) => self::CORE_COMPETENCIES[$key] ?? $key,
            array_filter($selected, fn ($k) => isset(self::CORE_COMPETENCIES[$k]))
        );
    }
}
