<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'role',
        'name',
        'email',
        'password',
        'two_factor_secret',
        'two_factor_enabled',
        'login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    // Account lockout constants
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_MINUTES    = 15;

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'two_factor_enabled' => 'boolean',
            'locked_until'       => 'datetime',
            'login_attempts'     => 'integer',
        ];
    }

    /** Is this account currently locked due to too many failed attempts? */
    public function isLockedOut(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /** Record a failed login attempt; lock if threshold is reached. */
    public function recordFailedLogin(): void
    {
        $attempts = $this->login_attempts + 1;

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $this->update([
                'login_attempts' => $attempts,
                'locked_until'   => now()->addMinutes(self::LOCKOUT_MINUTES),
            ]);
        } else {
            $this->update(['login_attempts' => $attempts]);
        }
    }

    /** Clear failed attempts after a successful login. */
    public function clearLoginAttempts(): void
    {
        if ($this->login_attempts > 0 || $this->locked_until !== null) {
            $this->update(['login_attempts' => 0, 'locked_until' => null]);
        }
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ── Role helpers ──────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isSchoolAdmin(): bool
    {
        return $this->role === 'school_admin';
    }

    /**
     * Does this user belong to the given tenant?
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenant_id === $tenant->id;
    }
}
