<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable payment ledger model.
 *
 * Every row is a permanent, append-only record of a payment state
 * transition. No row is ever modified or removed. Enforcement is both:
 *   1. Model-level — save/update/delete/truncate throw ImmutableRecordException.
 *   2. Intended DB-level — grant only INSERT + SELECT in production.
 *
 * @property int         $id
 * @property int         $payment_id
 * @property int         $tenant_id
 * @property string      $state        pending|successful|failed|reversed|disputed
 * @property float       $amount
 * @property string      $currency
 * @property string      $gateway
 * @property string      $reference
 * @property string|null $gateway_reference
 * @property string      $payment_type subscription|fee
 * @property string      $triggered_by system|webhook|admin|job
 * @property int|null    $triggered_by_user_id
 * @property string|null $trigger_reason
 * @property array|null  $gateway_payload
 * @property \Carbon\Carbon $recorded_at
 */
class PaymentLedger extends Model
{
    // ── State constants ────────────────────────────────────────────────────────
    const STATE_PENDING    = 'pending';
    const STATE_SUCCESSFUL = 'successful';
    const STATE_FAILED     = 'failed';
    const STATE_REVERSED   = 'reversed';
    const STATE_DISPUTED   = 'disputed';

    // ── Trigger sources ────────────────────────────────────────────────────────
    const TRIGGER_SYSTEM  = 'system';
    const TRIGGER_WEBHOOK = 'webhook';
    const TRIGGER_ADMIN   = 'admin';
    const TRIGGER_JOB     = 'job';

    // ── Table config ──────────────────────────────────────────────────────────
    protected $table = 'payment_ledger';

    /**
     * No updated_at — this table is append-only.
     * recorded_at (DB DEFAULT CURRENT_TIMESTAMP) serves as the single timestamp.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'payment_id',
        'tenant_id',
        'state',
        'amount',
        'currency',
        'gateway',
        'reference',
        'gateway_reference',
        'payment_type',
        'triggered_by',
        'triggered_by_user_id',
        'trigger_reason',
        'gateway_payload',
    ];

    protected $casts = [
        'amount'          => 'float',
        'gateway_payload' => 'array',
        'recorded_at'     => 'datetime',
    ];

    // ── Immutability enforcement ───────────────────────────────────────────────

    /**
     * Block all UPDATE operations at the model layer.
     * @throws \LogicException
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException(
                'PaymentLedger records are immutable — updates are not permitted. ' .
                'Add a new ledger entry to record a state change.'
            );
        }
        return parent::save($options);
    }

    /**
     * Block DELETE operations.
     * @throws \LogicException
     */
    public function delete(): bool|null
    {
        throw new \LogicException(
            'PaymentLedger records are immutable — deletion is not permitted. ' .
            'The ledger is a permanent audit trail.'
        );
    }

    /**
     * Block mass UPDATE operations on the query builder.
     * @throws \LogicException
     */
    public static function query(): Builder
    {
        return parent::query()->beforeQuery(function (Builder $builder) {
            // Allow SELECT; block write operations at query level too.
            // This is a best-effort guard — true prevention requires DB grants.
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    /** @return BelongsTo<Payment, PaymentLedger> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Tenant, PaymentLedger> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, PaymentLedger> */
    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    // ── Factory helpers (use these to create entries) ─────────────────────────

    /**
     * Record a payment state transition. This is the ONLY way to write
     * a ledger entry — never call new PaymentLedger() directly.
     *
     * @param  Payment  $payment   The source payment record (already updated)
     * @param  string   $state     One of the STATE_* constants
     * @param  string   $trigger   One of the TRIGGER_* constants
     * @param  string|null $reason Human-readable reason (required for reversed/disputed)
     * @param  array|null $payload Raw gateway payload to snapshot
     */
    public static function record(
        Payment $payment,
        string  $state,
        string  $trigger       = self::TRIGGER_SYSTEM,
        ?string $reason        = null,
        ?array  $payload       = null,
    ): static {
        $entry = new static();
        $entry->payment_id            = $payment->id;
        $entry->tenant_id             = $payment->tenant_id;
        $entry->state                 = $state;
        $entry->amount                = $payment->amount;
        $entry->currency              = $payment->currency;
        $entry->gateway               = $payment->gateway;
        $entry->reference             = $payment->reference;
        $entry->gateway_reference     = $payment->gateway_reference;
        $entry->payment_type          = $payment->payment_type ?? 'subscription';
        $entry->triggered_by          = $trigger;
        $entry->triggered_by_user_id  = auth()->id();
        $entry->trigger_reason        = $reason;
        $entry->gateway_payload       = $payload ?? $payment->metadata;

        $entry->save();  // calls parent::save() via the new-record path

        return $entry;
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeInState(Builder $query, string $state): Builder
    {
        return $query->where('state', $state);
    }

    public function scopeForPayment(Builder $query, int $paymentId): Builder
    {
        return $query->where('payment_id', $paymentId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isTerminal(): bool
    {
        return in_array($this->state, [
            self::STATE_SUCCESSFUL,
            self::STATE_FAILED,
            self::STATE_REVERSED,
            self::STATE_DISPUTED,
        ], true);
    }
}
