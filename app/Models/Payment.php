<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'tenant_id', 'subscription_id', 'fee_id', 'payment_type',
        'amount', 'currency',
        'gateway', 'reference', 'gateway_reference',
        'status', 'paid_at', 'webhook_received_at', 'metadata',
    ];

    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_FEE          = 'fee';

    protected $casts = [
        'paid_at'              => 'datetime',
        'webhook_received_at'  => 'datetime',
        'metadata'             => 'array',
    ];

    const STATUS_PENDING    = 'pending';
    const STATUS_SUCCESS    = 'success';
    const STATUS_FAILED     = 'failed';
    // Extended states — tracked in payment_ledger; payments table keeps 'success'
    const STATUS_REVERSED   = 'reversed';
    const STATUS_DISPUTED   = 'disputed';

    const GATEWAY_PAYSTACK = 'paystack';
    const GATEWAY_MOOLRE   = 'moolre';

    /** @return BelongsTo<Tenant, Payment> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Subscription, Payment> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<PaymentLedger> */
    public function ledger(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentLedger::class)->orderBy('id');
    }
}
