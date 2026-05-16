<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    use HasFactory, Notifiable;
    protected $fillable = [
        'uuid', 'slug', 'name', 'logo', 'favicon', 'address', 'phone', 'email',
        'domain', 'custom_domain', 'status', 'trial_ends_at', 'primary_color',
        'secondary_color', 'font_family',
        'contact_phone', 'contact_email', 'contact_name',
        'school_type', 'district', 'estimated_students',
        'grading_settings', 'website_content',
        'current_term_id',
        'sms_sender_id',
        'email_from_name', 'email_from_address', 'email_header_color',
        'report_card_template', 'report_card_footer',
        'login_welcome_text', 'login_bg_color',
        'registered_at', 'approved_at', 'approved_by', 'registration_token',
    ];

    protected $casts = [
        'trial_ends_at'      => 'datetime',
        'registered_at'      => 'datetime',
        'approved_at'        => 'datetime',
        'estimated_students' => 'integer',
        'grading_settings'   => 'array',
        'website_content'    => 'array',
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Whether the tenant's platform is accessible (trial, active, or grace period).
     * Grace-period schools are still accessible — they just see a payment banner.
     * This is intentionally consistent with AdminSubscriptionMiddleware's allow-list.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial', 'grace']);
    }

    /**
     * Whether the tenant is in a full-access state (not grace, not locked).
     */
    public function isFullyActive(): bool
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
     * Public URL for the school logo, regardless of which disk it lives on.
     */
    public function logoUrl(): ?string
    {
        if (! $this->logo) {
            return null;
        }
        $disk = config('filesystems.media_disk', 'public');
        return Storage::disk($disk)->url($this->logo);
    }

    /**
     * Public URL for the school favicon.
     */
    public function faviconUrl(): ?string
    {
        if (! $this->favicon) {
            return null;
        }
        $disk = config('filesystems.media_disk', 'public');
        return Storage::disk($disk)->url($this->favicon);
    }

    /**
     * Effective SMS sender ID — falls back to sanitised APP_NAME.
     */
    public function effectiveSmsSenderId(): string
    {
        if ($this->sms_sender_id) {
            return $this->sms_sender_id;
        }
        // Sanitise APP_NAME to max 11 alpha chars
        return substr(preg_replace('/[^A-Za-z]/', '', config('app.name', 'SchoolMS')), 0, 11);
    }

    /**
     * Effective "From" email name for outbound mail.
     */
    public function effectiveEmailFromName(): string
    {
        return $this->email_from_name ?: $this->name;
    }

    /**
     * Effective "From" email address for outbound mail.
     */
    public function effectiveEmailFromAddress(): string
    {
        return $this->email_from_address ?: config('mail.from.address', 'noreply@schoolms.com.gh');
    }

    /**
     * Resolved primary color with sensible default.
     */
    public function primaryColor(): string
    {
        return $this->primary_color ?? '#1a56db';
    }

    /**
     * Resolved secondary color with sensible default.
     */
    public function secondaryColor(): string
    {
        return $this->secondary_color ?? '#f3f4f6';
    }

    /**
     * Route notifications for mail — prefer contact_email, fall back to email.
     */
    public function routeNotificationForMail(): string
    {
        return $this->contact_email ?: $this->email;
    }
}
