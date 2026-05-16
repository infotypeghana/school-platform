<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment reconciliation engine.
 *
 * Responsibilities:
 *  - Detect payments with no matching subscription activation
 *  - Find subscriptions that were paid but not activated
 *  - Identify duplicate references
 *  - Generate reconciliation summary per tenant and globally
 *
 * Usage:
 *   app(ReconciliationService::class)->reconcile($from, $to);
 *   php artisan payments:reconcile --from=2026-01-01 --to=2026-05-31
 */
class ReconciliationService
{
    /**
     * Run reconciliation for a date range.
     * Returns a summary array with anomalies.
     */
    public function reconcile(Carbon $from, Carbon $to, ?Tenant $tenant = null): array
    {
        Log::info('reconciliation.started', [
            'from'      => $from->toDateString(),
            'to'        => $to->toDateString(),
            'tenant_id' => $tenant?->id,
        ]);

        return [
            'period'              => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'total_payments'      => $this->totalPayments($from, $to, $tenant),
            'total_amount'        => $this->totalAmount($from, $to, $tenant),
            'successful'          => $this->successfulPayments($from, $to, $tenant),
            'failed'              => $this->failedPayments($from, $to, $tenant),
            'pending'             => $this->pendingPayments($from, $to, $tenant),
            'orphaned_payments'   => $this->orphanedPayments($from, $to, $tenant),
            'duplicate_refs'      => $this->duplicateReferences($from, $to, $tenant),
            'unactivated_subs'    => $this->unactivatedSubscriptions($from, $to, $tenant),
            'gateway_breakdown'   => $this->gatewayBreakdown($from, $to, $tenant),
            'tenant_breakdown'    => $tenant ? [] : $this->tenantBreakdown($from, $to),
        ];
    }

    /** Payments with no associated subscription (orphaned). */
    public function orphanedPayments(Carbon $from, Carbon $to, ?Tenant $tenant = null): Collection
    {
        return Payment::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'success')
            ->whereNull('subscription_id')
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->select(['id', 'reference', 'amount', 'tenant_id', 'gateway', 'created_at'])
            ->get();
    }

    /** Subscriptions that are active but have no matching successful payment. */
    public function unactivatedSubscriptions(Carbon $from, Carbon $to, ?Tenant $tenant = null): Collection
    {
        return Subscription::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'active')
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'success'))
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->select(['id', 'tenant_id', 'amount', 'status', 'activated_at', 'created_at'])
            ->get();
    }

    /** Payment references that appear more than once (duplicate webhook risk). */
    public function duplicateReferences(Carbon $from, Carbon $to, ?Tenant $tenant = null): Collection
    {
        return Payment::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->select('reference', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('reference')
            ->having('count', '>', 1)
            ->get();
    }

    /**
     * Attempt to auto-heal orphaned payments:
     * finds the subscription for the tenant and activates it if unpaid.
     * Returns count of healed records.
     */
    public function autoHeal(Carbon $from, Carbon $to): int
    {
        $healed = 0;
        $orphans = $this->orphanedPayments($from, $to);

        foreach ($orphans as $payment) {
            try {
                $subscription = Subscription::where('tenant_id', $payment->tenant_id)
                    ->whereIn('status', ['grace', 'locked', 'trial'])
                    ->latest()
                    ->first();

                if ($subscription && ! $subscription->isAccessible()) {
                    $payment->update(['subscription_id' => $subscription->id]);
                    $subscription->transitionToActive();

                    Log::info('reconciliation.auto_healed', [
                        'payment_id'      => $payment->id,
                        'subscription_id' => $subscription->id,
                        'tenant_id'       => $payment->tenant_id,
                    ]);

                    $healed++;
                }
            } catch (\Throwable $e) {
                Log::error('reconciliation.heal_failed', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $healed;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function totalPayments(Carbon $from, Carbon $to, ?Tenant $tenant): int
    {
        return Payment::whereBetween('created_at', [$from, $to])
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->count();
    }

    private function totalAmount(Carbon $from, Carbon $to, ?Tenant $tenant): float
    {
        return (float) Payment::whereBetween('created_at', [$from, $to])
            ->where('status', 'success')
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->sum('amount');
    }

    private function successfulPayments(Carbon $from, Carbon $to, ?Tenant $tenant): int
    {
        return Payment::whereBetween('created_at', [$from, $to])
            ->where('status', 'success')
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->count();
    }

    private function failedPayments(Carbon $from, Carbon $to, ?Tenant $tenant): int
    {
        return Payment::whereBetween('created_at', [$from, $to])
            ->where('status', 'failed')
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->count();
    }

    private function pendingPayments(Carbon $from, Carbon $to, ?Tenant $tenant): int
    {
        return Payment::whereBetween('created_at', [$from, $to])
            ->where('status', 'pending')
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->count();
    }

    private function gatewayBreakdown(Carbon $from, Carbon $to, ?Tenant $tenant): Collection
    {
        return Payment::whereBetween('created_at', [$from, $to])
            ->where('status', 'success')
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->select('gateway', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('gateway')
            ->get();
    }

    private function tenantBreakdown(Carbon $from, Carbon $to): Collection
    {
        return Payment::whereBetween('created_at', [$from, $to])
            ->where('status', 'success')
            ->select('tenant_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('tenant_id')
            ->with('tenant:id,name,slug')
            ->get();
    }
}
