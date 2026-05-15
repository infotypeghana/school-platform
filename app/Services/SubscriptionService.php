<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function getStatus(Tenant $tenant): string
    {
        return Cache::remember(
            "subscription:status:{$tenant->id}",
            now()->addMinutes(5),
            fn () => $this->resolveStatus($tenant)
        );
    }

    public function flushCache(Tenant $tenant): void
    {
        Cache::forget("subscription:status:{$tenant->id}");
        Cache::forget("subscription:current:{$tenant->id}");
    }

    public function getCurrentSubscription(Tenant $tenant): ?Subscription
    {
        return Cache::remember(
            "subscription:current:{$tenant->id}",
            now()->addMinutes(5),
            fn () => $tenant->subscriptions()
                ->whereIn('status', ['trial', 'active', 'grace', 'locked'])
                ->latest()
                ->first()
        );
    }

    public function createTrialSubscription(Tenant $tenant): Subscription
    {
        // For trial creation there is no tenant in the container yet, so
        // AcademicTerm::current() will use the global is_current flag — correct.
        $term = AcademicTerm::where('is_current', true)->first();

        abort_unless($term !== null, 500, 'No current academic term configured.');

        $subscription = Subscription::create([
            'tenant_id'       => $tenant->id,
            'academic_year_id'=> $term->academic_year_id,
            'term_id'         => $term->id,
            'amount'          => 0,          // trials are always free
            'start_date'      => Carbon::today(),
            'end_date'        => $term->end_date,
            'grace_ends_at'   => $term->graceEndsAt(),
            'status'          => Subscription::STATUS_TRIAL,
            'is_trial'        => true,
            'activated_at'    => now(),
        ]);

        $this->flushCache($tenant);

        return $subscription;
    }

    /**
     * Calculate the invoice amount for a tenant based on a package and their
     * current active student count.  Respects the package's minimum-students floor.
     */
    public function calculateInvoiceAmount(Tenant $tenant, SubscriptionPackage $package): array
    {
        $studentCount = Student::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->count();

        $amount = $package->calculateAmount($studentCount);

        return [
            'student_count'     => $studentCount,
            'billable_students' => max($studentCount, $package->min_students),
            'price_per_student' => (float) $package->price_per_student,
            'amount'            => $amount,
        ];
    }

    public function activateFromPayment(Payment $payment): void
    {
        $subscription = $payment->subscription;
        $tenant       = $payment->tenant;

        if (! $subscription || ! $tenant) {
            Log::error('activateFromPayment: missing subscription or tenant', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        $subscription->transitionToActive();
        $this->flushCache($tenant);

        Log::info('Subscription activated via payment', [
            'tenant_id'       => $tenant->id,
            'subscription_id' => $subscription->id,
            'payment_ref'     => $payment->reference,
        ]);
    }

    public function transitionExpiredToGrace(): int
    {
        $count = 0;

        Subscription::whereIn('status', [Subscription::STATUS_TRIAL, Subscription::STATUS_ACTIVE])
            ->whereHas('term', fn ($q) => $q->whereDate('end_date', '<', Carbon::today()))
            ->each(function (Subscription $sub) use (&$count) {
                $sub->transitionToGrace();
                $tenant = $sub->tenant;
                if ($tenant) {
                    $this->flushCache($tenant);
                }
                $count++;

                Log::info('Subscription transitioned to grace', [
                    'tenant_id'       => $sub->tenant_id,
                    'subscription_id' => $sub->id,
                ]);
            });

        return $count;
    }

    public function transitionGraceToLocked(): int
    {
        $count = 0;

        Subscription::where('status', Subscription::STATUS_GRACE)
            ->where('grace_ends_at', '<', now())
            ->each(function (Subscription $sub) use (&$count) {
                $sub->transitionToLocked();
                $tenant = $sub->tenant;
                if ($tenant) {
                    $this->flushCache($tenant);
                }
                $count++;

                Log::info('Subscription locked — grace expired', [
                    'tenant_id'       => $sub->tenant_id,
                    'subscription_id' => $sub->id,
                ]);
            });

        return $count;
    }

    private function resolveStatus(Tenant $tenant): string
    {
        $sub = $tenant->subscriptions()
            ->whereIn('status', ['trial', 'active', 'grace', 'locked', 'suspended'])
            ->latest()
            ->first();

        return $sub ? $sub->status : 'none';
    }
}
