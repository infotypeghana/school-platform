<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CheckSubscriptionStatusJob;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Subscription;
use App\Models\SubscriptionNotification;
use App\Models\Tenant;
use App\Notifications\SubscriptionExpiryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies CheckSubscriptionStatusJob:
 *   - trial/active → grace when term has ended
 *   - grace → locked when grace_ends_at has passed
 *   - notification scheduling: 14d, 7d, 2d, expiry-day, grace-3d, grace-1d, locked
 *   - notifications are NOT re-sent if already recorded
 */
class CheckSubscriptionStatusJobTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid'   => Str::uuid(),
            'slug'   => 'job-test-school',
            'name'   => 'Job Test School',
            'email'  => 'admin@jobtest.edu.gh',
            'status' => 'active',
        ]);

        Notification::fake();
    }

    // ── State transitions ─────────────────────────────────────────────────────

    public function test_active_subscription_transitions_to_grace_when_term_ended(): void
    {
        $sub = $this->createSubscription('active', now()->subDays(5));

        dispatch(new CheckSubscriptionStatusJob());

        $sub->refresh();
        $this->assertEquals('grace', $sub->status);
        $this->assertEquals('grace', $this->tenant->fresh()->status);
    }

    public function test_trial_subscription_transitions_to_grace_when_term_ended(): void
    {
        $sub = $this->createSubscription('trial', now()->subDays(3));

        dispatch(new CheckSubscriptionStatusJob());

        $sub->refresh();
        $this->assertEquals('grace', $sub->status);
    }

    public function test_active_subscription_not_transitioned_when_term_not_ended(): void
    {
        $sub = $this->createSubscription('active', now()->addDays(10));

        dispatch(new CheckSubscriptionStatusJob());

        $sub->refresh();
        $this->assertEquals('active', $sub->status);
    }

    public function test_grace_subscription_transitions_to_locked_when_grace_expired(): void
    {
        $sub = $this->createSubscription('grace', now()->subDays(10));
        $sub->update(['grace_ends_at' => now()->subHour()]);

        dispatch(new CheckSubscriptionStatusJob());

        $sub->refresh();
        $this->assertEquals('locked', $sub->status);
        $this->assertEquals('locked', $this->tenant->fresh()->status);
    }

    public function test_grace_subscription_not_locked_while_grace_still_active(): void
    {
        $sub = $this->createSubscription('grace', now()->subDays(10));
        $sub->update(['grace_ends_at' => now()->addDays(2)]);

        dispatch(new CheckSubscriptionStatusJob());

        $sub->refresh();
        $this->assertEquals('grace', $sub->status);
    }

    // ── Notification scheduling ───────────────────────────────────────────────

    public function test_sends_14_day_notification(): void
    {
        $this->createSubscription('active', now()->addDays(14));

        dispatch(new CheckSubscriptionStatusJob());

        Notification::assertSentTo($this->tenant, SubscriptionExpiryNotification::class,
            fn ($n) => $n->type === '14_days_before'
        );
    }

    public function test_sends_7_day_notification(): void
    {
        $this->createSubscription('active', now()->addDays(7));

        dispatch(new CheckSubscriptionStatusJob());

        Notification::assertSentTo($this->tenant, SubscriptionExpiryNotification::class,
            fn ($n) => $n->type === '7_days_before'
        );
    }

    public function test_sends_2_day_notification(): void
    {
        $this->createSubscription('active', now()->addDays(2));

        dispatch(new CheckSubscriptionStatusJob());

        Notification::assertSentTo($this->tenant, SubscriptionExpiryNotification::class,
            fn ($n) => $n->type === '2_days_before'
        );
    }

    public function test_sends_expiry_day_notification(): void
    {
        $this->createSubscription('active', now()); // ends today

        dispatch(new CheckSubscriptionStatusJob());

        Notification::assertSentTo($this->tenant, SubscriptionExpiryNotification::class,
            fn ($n) => $n->type === 'expiry_day'
        );
    }

    public function test_sends_grace_3_days_notification(): void
    {
        $sub = $this->createSubscription('grace', now()->subDays(10));
        $sub->update(['grace_ends_at' => now()->addDays(3)]);

        dispatch(new CheckSubscriptionStatusJob());

        Notification::assertSentTo($this->tenant, SubscriptionExpiryNotification::class,
            fn ($n) => $n->type === 'grace_3_days_left'
        );
    }

    public function test_sends_grace_1_day_notification(): void
    {
        $sub = $this->createSubscription('grace', now()->subDays(10));
        $sub->update(['grace_ends_at' => now()->addDay()]);

        dispatch(new CheckSubscriptionStatusJob());

        Notification::assertSentTo($this->tenant, SubscriptionExpiryNotification::class,
            fn ($n) => $n->type === 'grace_1_day_left'
        );
    }

    public function test_sends_locked_notification_on_lock_day(): void
    {
        $sub = $this->createSubscription('locked', now()->subDays(10));
        $sub->update(['locked_at' => now()]);

        dispatch(new CheckSubscriptionStatusJob());

        Notification::assertSentTo($this->tenant, SubscriptionExpiryNotification::class,
            fn ($n) => $n->type === 'grace_expired_locked'
        );
    }

    public function test_does_not_resend_notification_already_recorded(): void
    {
        $sub = $this->createSubscription('active', now()->addDays(7));

        // Simulate a prior notification already stored
        SubscriptionNotification::create([
            'tenant_id'       => $this->tenant->id,
            'subscription_id' => $sub->id,
            'type'            => '7_days_before',
            'channel'         => 'email',
            'sent_at'         => now()->subHour(),
            'delivered'       => true,
        ]);

        dispatch(new CheckSubscriptionStatusJob());

        Notification::assertNotSentTo($this->tenant, SubscriptionExpiryNotification::class,
            fn ($n) => $n->type === '7_days_before'
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createSubscription(string $status, \DateTimeInterface $endDate): Subscription
    {
        $year = AcademicYear::create(['year_label' => 'CheckJob-Test', 'is_current' => false]);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'term_number'      => 1,
            'term_name'        => 'Term 1',
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => \Carbon\Carbon::instance($endDate)->toDateString(),
            'is_current'       => false,
        ]);

        return Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $year->id,
            'term_id'          => $term->id,
            'amount'           => 500,
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => \Carbon\Carbon::instance($endDate)->toDateString(),
            'status'           => $status,
            'is_trial'         => $status === 'trial',
            'activated_at'     => now()->subDays(60),
        ]);
    }
}
