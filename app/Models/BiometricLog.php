<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricLog extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id', 'device_id', 'device_user_id', 'verified_at',
        'verify_type', 'direction', 'source', 'is_processed', 'attendance_id',
    ];

    protected $casts = [
        'verified_at'  => 'datetime',
        'is_processed' => 'boolean',
        'verify_type'  => 'integer',
        'direction'    => 'integer',
    ];

    // ── Verify type labels ────────────────────────────────────────────────────
    public const VERIFY_TYPES = [
        0  => 'Fingerprint',
        1  => 'Fingerprint (alt.)',
        4  => 'RFID Card',
        15 => 'Face',
    ];

    // ── Direction labels ──────────────────────────────────────────────────────
    public const DIRECTIONS = [
        0 => 'Check In',
        1 => 'Check Out',
        2 => 'Break Out',
        3 => 'Break In',
        4 => 'OT In',
        5 => 'OT Out',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function getVerifyLabelAttribute(): string
    {
        return self::VERIFY_TYPES[$this->verify_type] ?? "Type {$this->verify_type}";
    }

    public function getDirectionLabelAttribute(): string
    {
        return self::DIRECTIONS[$this->direction] ?? "Direction {$this->direction}";
    }
}
