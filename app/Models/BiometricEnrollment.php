<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricEnrollment extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id', 'device_id', 'device_user_id', 'person_type', 'person_id',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    /**
     * Dynamically resolve the linked student or teacher.
     */
    public function person(): Student|Teacher|null
    {
        return match ($this->person_type) {
            'student' => Student::withoutTenantScope()->find($this->person_id),
            'teacher' => Teacher::withoutTenantScope()->find($this->person_id),
            default   => null,
        };
    }

    public function getPersonNameAttribute(): string
    {
        return $this->person()?->full_name ?? "(Unknown #{$this->person_id})";
    }
}
