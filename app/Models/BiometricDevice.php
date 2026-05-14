<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricDevice extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'tenant_id', 'name', 'device_serial', 'ip_address', 'port',
        'password', 'model', 'location', 'is_active', 'last_sync_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_sync_at' => 'datetime',
        'password'     => 'integer',
        'port'         => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BiometricLog::class, 'device_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(BiometricEnrollment::class, 'device_id');
    }

    public function getIsTcpCapableAttribute(): bool
    {
        return ! empty($this->ip_address);
    }

    public function getStatusLabelAttribute(): string
    {
        if (! $this->is_active) {
            return 'Inactive';
        }

        if (! $this->last_sync_at) {
            return 'Never synced';
        }

        return 'Last sync ' . $this->last_sync_at->diffForHumans();
    }
}
