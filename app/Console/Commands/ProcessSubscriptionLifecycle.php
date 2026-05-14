<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SubscriptionNotification;
use App\Notifications\SubscriptionExpiryNotification;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Run daily via scheduler to:
 *  1. Send reminder emails at -14, -7, -2 days before term end
 *  2. Send "expired" email on the expiry day
 *  3. Transition expired → grace
 *  4. Send grace warnings at 3 days and 1 day remaining
 *  5. Transition grace → locked when grace_ends_at passes
 *  6. Send "locked" notification
 */
class ProcessSubscriptionLifecycle extends Command
{
    protected $signature   = 'subscriptions:lifecycle {--dry-run : Log actions without making changes}';
    protected $description = 'Send subscription reminder emails and transition subscription states';

    public function __construct(private SubscriptionService $service) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $today  = Carbon::today();

        $this->info($dryRun ? '[DRY RUN] Processing subscription lifecycle…' : 'Processing subscription lifecycle…');

        // ── 1. Pre-expiry reminders (active/trial subscriptions) ──────────────
        $this->sendPreExpiryReminders($today, $dryRun);

        // ── 2. Expire → Grace transition ──────────────────────────────────────
        $transitioned = $dryRun ? 0 : $this->service->transitionExpiredToGrace();
        $this->line("  Transitioned to grace: {$transitioned}");

        // ── 3. Grace-period warnings ───────────────────────────────────────────
        $this->sendGraceWarnings($today, $dryRun);

        // ── 4. Grace → Locked transition ──────────────────────────────────────
        $locked = $dryRun ? 0 : $this->service->transitionGraceToLocked();
        $this->line("  Locked (grace expired): {$locked}");

        // ── 5. Notify newly-locked subscriptions ──────────────────────────────
        $this->notifyNewlyLocked($today, $dryRun);

        $this->info('Done.');
        return self::SUCCESS;
    }

    // ── Pre-expiry reminders ────────────────────────────────────────────────
    private function sendPreExpiryReminders(Carbon $today, bool $dryRun): void
    {
        $milestones = [
            14 => '14_days_before',
            7  => '7_days_before',
            2  => '2_days_before',
            0  => 'expiry_day',
        ];

        foreach ($milestones as $daysAhead => $type) {
            $targetDate = $today->copy()->addDays($daysAhead)->toDateString();

            $subs = Subscription::whereIn('status', ['trial', 'active'])
                ->whereHas('term', fn ($q) => $q->whereDate('end_date', $targetDate))
                ->with(['tenant', 'term', 'term.academicYear'])
                ->get();

            foreach ($subs as $sub) {
                $this->line("  [{$type}] {$sub->tenant->name} (sub #{$sub->id})");

                if (! $dryRun) {
                    $this->dispatchNotification($sub, $type);
                }
            }
        }
    }

    // ── Grace-period warnings ───────────────────────────────────────────────
    private function sendGraceWarnings(Carbon $today, bool $dryRun): void
    {
        $warnings = [
            3 => 'grace_3_days_left',
            1 => 'grace_1_day_left',
        ];

        foreach ($warnings as $daysLeft => $type) {
            // grace_ends_at is on that exact date
            $targetDate = $today->copy()->addDays($daysLeft)->toDateString();

            $subs = Subscription::where('status', Subscription::STATUS_GRACE)
                ->whereDate('grace_ends_at', $targetDate)
                ->with(['tenant', 'term', 'term.academicYear'])
                ->get();

            foreach ($subs as $sub) {
                $this->line("  [{$type}] {$sub->tenant->name} (sub #{$sub->id})");

                if (! $dryRun) {
                    $this->dispatchNotification($sub, $type);
                }
            }
        }
    }

    // ── Notify subscriptions locked today ───────────────────────────────────
    private function notifyNewlyLocked(Carbon $today, bool $dryRun): void
    {
        // Find subs that became locked today (grace_ends_at = yesterday or today)
        $subs = Subscription::where('status', Subscription::STATUS_LOCKED)
            ->whereDate('grace_ends_at', '>=', $today->copy()->subDay()->toDateString())
            ->whereDate('grace_ends_at', '<=', $today->toDateString())
            ->with(['tenant', 'term', 'term.academicYear'])
            ->get();

        foreach ($subs as $sub) {
            $this->line("  [grace_expired_locked] {$sub->tenant->name} (sub #{$sub->id})");

            if (! $dryRun) {
                $this->dispatchNotification($sub, 'grace_expired_locked');
            }
        }
    }

    // ── Idempotent send helper ──────────────────────────────────────────────
    /**
     * Send a notification only if one of the same type hasn't already been
     * dispatched today for this subscription. Guards against duplicate sends
     * when the command is run more than once in a day (e.g. manual re-run).
     */
    private function dispatchNotification(Subscription $sub, string $type): void
    {
        $alreadySent = SubscriptionNotification::where('subscription_id', $sub->id)
            ->where('type', $type)
            ->whereDate('sent_at', today())
            ->exists();

        if ($alreadySent) {
            $this->line("    [skip] {$type} already sent today for sub #{$sub->id}");
            return;
        }

        try {
            $sub->tenant->notify(new SubscriptionExpiryNotification($sub, $type));

            SubscriptionNotification::create([
                'tenant_id'       => $sub->tenant_id,
                'subscription_id' => $sub->id,
                'type'            => $type,
                'channel'         => 'mail',
                'sent_at'         => now(),
                'delivered'       => true,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to send {$type} for sub #{$sub->id}: " . $e->getMessage());
        }
    }
}
