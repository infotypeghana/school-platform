<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Announcement extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'tenant_id', 'created_by', 'title', 'body',
        'audience', 'school_class_id', 'is_pinned',
        'published_at', 'expires_at',
    ];

    protected $casts = [
        'is_pinned'    => 'boolean',
        'published_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getIsActiveAttribute(): bool
    {
        $now = now();
        $afterPublished = is_null($this->published_at) || $this->published_at->lte($now);
        $beforeExpiry   = is_null($this->expires_at)   || $this->expires_at->gt($now);

        return $afterPublished && $beforeExpiry;
    }

    public function getAudienceLabelAttribute(): string
    {
        return match ($this->audience) {
            'all'      => 'Everyone',
            'teachers' => 'Teachers',
            'parents'  => 'Parents',
            'class'    => $this->schoolClass?->full_name ?? 'Class',
            default    => ucfirst($this->audience),
        };
    }
}
