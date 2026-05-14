<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\SubscriptionNotification;
use App\Notifications\SubscriptionExpiryNotification;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckSubscriptionStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SubscriptionService $service): void
    {
        Log::info('CheckSubscriptionStatusJob running', ['at' => now()->toDateTimeString()]);

        // 1. Transition active/trial → grace when term has ended
        $toGrace = $service->transitionExpiredToGrace();
        Log::info("Transitioned {$toGrace} subscriptions to grace.");

        // 2. Transition grace → locked when grace period has ended
        $toLocked = $service->transitionGraceToLocked();
        Log::info("Locked {$toLocked} subscriptions.");

        // 3. Send pre-expiry and grace notifications
        $this->sendScheduledNotifications();
    }

    private function sendScheduledNotifications(): void
    {
        $today = Carbon::today();

        // Pre-expiry — active/trial subscriptions
        // chunk(100) instead of get() avoids loading the entire subscriptions table into
        // memory on large installations.
        Subscription::whereIn('status', [Subscription::STATUS_TRIAL, Subscription::STATUS_ACTIVE])
            ->with(['tenant', 'term'])
            ->chunk(100, function ($subs) use ($today) {
                foreach ($subs as $sub) {
                    $daysToExpiry = $today->diffInDays($sub->end_date, false);

                    $map = [
                        14 => '14_days_before',
                        7  => '7_days_before',
                        2  => '2_days_before',
                        0  => 'expiry_day',
                    ];

                    foreach ($map as $days => $type) {
                        if ((int) $daysToExpiry === $days && ! $sub->notificationSent($type)) {
                            $this->sendNotification($sub, $type);
                        }
                    }
                }
            });

        // Grace period notifications
        Subscription::where('status', Subscription::STATUS_GRACE)
            ->with(['tenant', 'term'])
            ->chunk(100, function ($subs) {
                foreach ($subs as $sub) {
                    $remaining = $sub->graceDaysRemaining();

                    $map = [
                        3 => 'grace_3_days_left',
                        1 => 'grace_1_day_left',
                    ];

                    foreach ($map as $days => $type) {
                        if ($remaining === $days && ! $sub->notificationSent($type)) {
                            $this->sendNotification($sub, $type);
                        }
                    }
                }
            });

        // Just-locked notifications
        Subscription::where('status', Subscription::STATUS_LOCKED)
            ->whereNotNull('locked_at')
            ->whereDate('locked_at', Carbon::today())
            ->with('tenant')
            ->chunk(100, function ($subs) {
                foreach ($subs as $sub) {
                    if (! $sub->notificationSent('grace_expired_locked')) {
                        $this->sendNotification($sub, 'grace_expired_locked');
                    }
                }
            });
    }

    private function sendNotification(Subscription $sub, string $type): void
    {
        try {
            $sub->tenant->notify(new SubscriptionExpiryNotification($sub, $type));

            SubscriptionNotification::create([
                'tenant_id'       => $sub->tenant_id,
                'subscription_id' => $sub->id,
                'type'            => $type,
                'channel'         => 'email',
                'sent_at'         => now(),
                'delivered'       => true,
            ]);

            Log::info("Notification [{$type}] sent to tenant {$sub->tenant_id}");
        } catch (\Throwable $e) {
            Log::error("Failed to send [{$type}] to tenant {$sub->tenant_id}: {$e->getMessage()}");
        }
    }
}
