<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'tenant_id', 'subscription_id', 'amount', 'currency',
        'gateway', 'reference', 'gateway_reference',
        'status', 'paid_at', 'webhook_received_at', 'metadata',
    ];

    protected $casts = [
        'paid_at'              => 'datetime',
        'webhook_received_at'  => 'datetime',
        'metadata'             => 'array',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED  = 'failed';

    const GATEWAY_PAYSTACK = 'paystack';
    const GATEWAY_MOOLRE   = 'moolre';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
