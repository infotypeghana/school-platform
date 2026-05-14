<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'user_name', 'action',
        'auditable_type', 'auditable_id', 'auditable_label',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    /**
     * Record a manual audit event (for actions not covered by the observer).
     */
    public static function record(
        string $action,
        string $description,
        mixed  $model    = null,
        array  $extra    = [],
    ): void {
        static::create(array_merge([
            'tenant_id'       => app()->bound('currentTenant') ? app('currentTenant')?->id : null,
            'user_id'         => auth()->id(),
            'user_name'       => auth()->user()?->name,
            'action'          => $action,
            'auditable_type'  => $model ? get_class($model) : null,
            'auditable_id'    => $model?->id,
            'auditable_label' => $description,
            'ip_address'      => request()->ip(),
            'user_agent'      => substr(request()->userAgent() ?? '', 0, 200),
        ], $extra));
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getActionBadgeAttribute(): string
    {
        return match ($this->action) {
            'created' => 'bg-emerald-100 text-emerald-700',
            'updated' => 'bg-blue-100 text-blue-700',
            'deleted' => 'bg-red-100 text-red-700',
            'login'   => 'bg-violet-100 text-violet-700',
            'export'  => 'bg-amber-100 text-amber-700',
            default   => 'bg-gray-100 text-gray-600',
        };
    }

    public function getModelNameAttribute(): string
    {
        if (! $this->auditable_type) {
            return '—';
        }
        return class_basename($this->auditable_type);
    }
}
