<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Tenant extends Model
{
    use HasFactory, Notifiable;
    protected $fillable = [
        'uuid', 'slug', 'name', 'logo', 'address', 'phone', 'email',
        'domain', 'status', 'trial_ends_at', 'primary_color',
        'contact_phone', 'contact_email', 'grading_settings',
    ];

    protected $casts = [
        'trial_ends_at'    => 'datetime',
        'grading_settings' => 'array',
    ];

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
