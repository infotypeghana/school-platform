<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id', 'student_id', 'academic_year_id',
        'from_class_id', 'to_class_id',
        'action', 'promoted_by', 'notes',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function fromClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'from_class_id');
    }

    public function toClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'to_class_id');
    }

    public function promotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function actionLabel(): string
    {
        return match ($this->action) {
            'promoted'    => 'Promoted',
            'held_back'   => 'Held Back',
            'graduated'   => 'Graduated',
            'transferred' => 'Transferred',
            default       => ucfirst($this->action),
        };
    }

    public function actionBadgeClass(): string
    {
        return match ($this->action) {
            'promoted'    => 'bg-blue-100 text-blue-700',
            'held_back'   => 'bg-amber-100 text-amber-700',
            'graduated'   => 'bg-green-100 text-green-700',
            'transferred' => 'bg-purple-100 text-purple-700',
            default       => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Human-readable outcome line, e.g. "Basic 4 → Basic 5" or "Basic 4 → Graduated".
     */
    public function movementLabel(): string
    {
        $from = $this->fromClass?->full_name ?? '—';

        if ($this->action === 'graduated') {
            return "{$from} → Graduated";
        }

        if ($this->action === 'held_back') {
            return "{$from} → {$from} (held back)";
        }

        $to = $this->toClass?->full_name ?? '—';
        return "{$from} → {$to}";
    }
}
