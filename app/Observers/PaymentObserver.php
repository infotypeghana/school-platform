<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\PaymentLedger;
use Illuminate\Support\Facades\Log;

/**
 * PaymentObserver — writes an immutable ledger entry on every Payment transition.
 *
 * Triggered by: Payment::observe(PaymentObserver::class)
 *
 * Ledger entries are written:
 *   - created  → initial state (always 'pending' on creation)
 *   - updated  → whenever `status` changes
 *
 * The status field on `payments` uses the legacy values ('success') while the
 * ledger uses normalized values ('successful'). This mapping is done here.
 */
class PaymentObserver
{
    /**
     * Map Payment::STATUS_* → PaymentLedger::STATE_*
     *
     * The payments table uses 'success' (legacy); the ledger uses 'successful'
     * (more explicit). All other states match 1:1.
     */
    private const STATUS_MAP = [
        'pending'    => PaymentLedger::STATE_PENDING,
        'success'    => PaymentLedger::STATE_SUCCESSFUL,
        'failed'     => PaymentLedger::STATE_FAILED,
        'reversed'   => PaymentLedger::STATE_REVERSED,
        'disputed'   => PaymentLedger::STATE_DISPUTED,
    ];

    /**
     * Record initial 'pending' state when a payment is first created.
     */
    public function created(Payment $payment): void
    {
        if (app()->runningUnitTests() && ! config('audit.enabled_in_tests', false)) {
            return;
        }

        $this->record($payment, $payment->status, 'system', 'Payment initiated');
    }

    /**
     * Record a new ledger entry whenever the `status` column changes.
     */
    public function updated(Payment $payment): void
    {
        if (app()->runningUnitTests() && ! config('audit.enabled_in_tests', false)) {
            return;
        }

        if (! $payment->wasChanged('status')) {
            return;
        }

        $oldStatus = $payment->getOriginal('status');
        $newStatus = $payment->status;

        $trigger = $this->detectTrigger($payment);
        $reason  = $this->buildReason($oldStatus, $newStatus, $payment);

        $this->record($payment, $newStatus, $trigger, $reason);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function record(Payment $payment, string $rawStatus, string $trigger, ?string $reason): void
    {
        $state = self::STATUS_MAP[$rawStatus] ?? $rawStatus;

        try {
            PaymentLedger::record(
                payment: $payment,
                state:   $state,
                trigger: $trigger,
                reason:  $reason,
                payload: $payment->metadata,
            );
        } catch (\Throwable $e) {
            // Ledger failures must NEVER break the payment flow.
            // Log at CRITICAL level so Sentry captures it, but swallow the exception.
            Log::critical('[PAYMENT_LEDGER] Failed to write ledger entry', [
                'payment_id' => $payment->id,
                'state'      => $state,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Detect how this change was triggered.
     */
    private function detectTrigger(Payment $payment): string
    {
        // Webhook changes arrive outside the normal request cycle with no auth user
        if ($payment->wasChanged('webhook_received_at') || ! auth()->check()) {
            return PaymentLedger::TRIGGER_WEBHOOK;
        }

        // Admin-initiated (e.g., manual status override in super admin panel)
        if (auth()->check()) {
            return PaymentLedger::TRIGGER_ADMIN;
        }

        return PaymentLedger::TRIGGER_SYSTEM;
    }

    /**
     * Build a human-readable transition reason.
     */
    private function buildReason(string $from, string $to, Payment $payment): string
    {
        $fromLabel = self::STATUS_MAP[$from] ?? $from;
        $toLabel   = self::STATUS_MAP[$to]   ?? $to;

        $reason = "Status changed from {$fromLabel} to {$toLabel}";

        if ($to === 'reversed') {
            $reason = 'Payment reversed — ' . ($payment->metadata['reversal_reason'] ?? 'reason not provided');
        } elseif ($to === 'disputed') {
            $reason = 'Payment disputed — ' . ($payment->metadata['dispute_reason'] ?? 'chargeback or customer dispute');
        } elseif ($to === 'success') {
            $reason = 'Payment confirmed via ' . ($payment->gateway ?? 'gateway') . ' webhook';
        } elseif ($to === 'failed') {
            $reason = 'Payment failed — ' . ($payment->metadata['failure_reason'] ?? 'gateway declined');
        }

        return $reason;
    }
}
