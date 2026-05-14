<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionNotification extends Model
{
    protected $fillable = [
        'tenant_id', 'subscription_id', 'type', 'channel', 'sent_at', 'delivered',
    ];

    protected $casts = [
        'sent_at'   => 'datetime',
        'delivered' => 'boolean',
    ];

    const TYPES = [
        '14_days_before',
        '7_days_before',
        '2_days_before',
        'expiry_day',
        'grace_3_days_left',
        'grace_1_day_left',
        'grace_expired_locked',
        'payment_confirmed',
    ];

    const CHANNEL_EMAIL  = 'email';
    const CHANNEL_SMS    = 'sms';
    const CHANNEL_IN_APP = 'in_app';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
