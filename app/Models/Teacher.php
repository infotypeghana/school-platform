<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'staff_id', 'first_name', 'last_name',
        'email', 'phone', 'gender', 'photo',
        'qualification', 'specialization', 'status',
        'joined_date',
        // Teacher portal
        'portal_password', 'portal_active', 'portal_last_login',
    ];

    protected $hidden = ['portal_password'];

    protected $casts = [
        'joined_date'        => 'date',
        'portal_active'      => 'boolean',
        'portal_last_login'  => 'datetime',
    ];

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'class_teacher_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
