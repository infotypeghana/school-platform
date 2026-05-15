<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Tenant extends Model
{
    use HasFactory, Notifiable;
    protected $fillable = [
        'uuid', 'slug', 'name', 'logo', 'address', 'phone', 'email',
        'domain', 'status', 'trial_ends_at', 'primary_color',
        'contact_phone', 'contact_email', 'grading_settings', 'website_content',
        'current_term_id',
    ];

    protected $casts = [
        'trial_ends_at'    => 'datetime',
        'grading_settings' => 'array',
        'website_content'  => 'array',
    ];

    /**
     * Get a website_content field with a fallback default.
     */
    public function wc(string $key, mixed $default = ''): mixed
    {
        return $this->website_content[$key] ?? $default;
    }

    // ── Academic calendar ─────────────────────────────────────────────────────

    /** The term this school has set as their active term. */
    public function currentTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'current_term_id');
    }

    /**
     * Resolve the active academic term for this tenant.
     * Falls back to the global `is_current` flag if no tenant term is set.
     */
    public function resolveCurrentTerm(): ?AcademicTerm
    {
        if ($this->current_term_id) {
            return $this->currentTerm;
        }
        return AcademicTerm::where('is_current', true)->first();
    }

    /**
     * Resolve the active academic year for this tenant.
     */
    public function resolveCurrentYear(): ?AcademicYear
    {
        return $this->resolveCurrentTerm()?->academicYear;
    }

    // ── Subscriptions ─────────────────────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['trial', 'active', 'grace'])
            ->latest();
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['trial', 'active', 'grace', 'locked'])
            ->latest()
            ->first();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial']);
    }

    public function isInGrace(): bool
    {
        return $this->status === 'grace';
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['locked', 'suspended']);
    }

    public function getSubscriptionStatus(): string
    {
        $sub = $this->currentSubscription();
        return $sub ? $sub->status : 'none';
    }

    /**
     * Route notifications for mail — prefer contact_email, fall back to email.
     */
    public function routeNotificationForMail(): string
    {
        return $this->contact_email ?: $this->email;
    }
}
