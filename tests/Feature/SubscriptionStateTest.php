<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionStateTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;
    private Tenant $tenant;
    private AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionService::class);

        // Create an academic year + term
        $year = AcademicYear::create([
            'year_label' => '2024/2025',
            'is_current' => true,
        ]);

        $this->term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'term_number'      => 3,
            'term_name'        => 'Third Term',
            'start_date'       => now()->subDays(90)->toDateString(),
            'end_date'         => now()->subDay()->toDateString(), // ended yesterday
            'is_current'       => true,
        ]);

        $this->tenant = Tenant::create([
            'uuid'   => \Illuminate\Support\Str::uuid(),
            'slug'   => 'test-school',
            'name'   => 'Test School',
            'email'  => 'test@school.edu.gh',
            'status' => 'trial',
        ]);
    }

    // ── Trial subscription creation ───────────────────────────────────────────

    public function test_creates_trial_subscription_for_current_term(): void
    {
        $sub = $this->service->createTrialSubscription($this->tenant);

        $this->assertSame(Subscription::STATUS_TRIAL, $sub->status);
        $this->assertTrue($sub->is_trial);
        $this->assertSame($this->term->id, $sub->term_id);
        $this->assertSame(0.0, (float) $sub->amount);
        $this->assertNotNull($sub->activated_at);
    }

    // ── transitionToGrace() ───────────────────────────────────────────────────

    public function test_transition_to_grace_sets_grace_ends_at(): void
    {
        $sub = $this->createActiveSubscription();
        $sub->transitionToGrace();
        $sub->refresh();

        $this->assertSame(Subscription::STATUS_GRACE, $sub->status);
        $this->assertNotNull($sub->grace_ends_at);

        $expectedGraceDays = config('billing.grace_period_days', 5);
        $this->assertTrue(
            $sub->grace_ends_at->isAfter(now()->addDays($expectedGraceDays - 1))
        );
    }

    public function test_grace_days_remaining_decrements_correctly(): void
    {
        $sub = $this->createGraceSubscription(daysLeft: 3);

        $this->assertSame(3, $sub->graceDaysRemaining());
    }

    public function test_grace_days_remaining_zero_when_expired(): void
    {
        $sub = $this->createGraceSubscription(daysLeft: -1);

        $this->assertSame(0, $sub->graceDaysRemaining());
    }

    // ── transitionToLocked() ──────────────────────────────────────────────────

    public function test_transition_to_locked_sets_locked_at(): void
    {
        $sub = $this->createGraceSubscription(daysLeft: 0);
        $sub->transitionToLocked();
        $sub->refresh();

        $this->assertSame(Subscription::STATUS_LOCKED, $sub->status);
        $this->assertNotNull($sub->locked_at);
        $this->assertTrue($sub->isLocked());
    }

    // ── transitionToActive() ──────────────────────────────────────────────────

    public function test_transition_to_active_via_payment(): void
    {
        $sub = $this->createGraceSubscription(daysLeft: 2);
        $sub->transitionToActive();
        $sub->refresh();

        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertNotNull($sub->activated_at);
        $this->assertTrue($sub->isAccessible());
    }

    // ── isAccessible() ───────────────────────────────────────────────────────

    public function test_trial_active_grace_are_accessible(): void
    {
        foreach ([Subscription::STATUS_TRIAL, Subscription::STATUS_ACTIVE, Subscription::STATUS_GRACE] as $status) {
            $sub = $this->createSubscriptionWithStatus($status);
            $this->assertTrue($sub->isAccessible(), "{$status} should be accessible");
        }
    }

    public function test_locked_suspended_are_not_accessible(): void
    {
        foreach ([Subscription::STATUS_LOCKED, Subscription::STATUS_SUSPENDED] as $status) {
            $sub = $this->createSubscriptionWithStatus($status);
            $this->assertFalse($sub->isAccessible(), "{$status} should not be accessible");
        }
    }

    // ── Bulk transitions via SubscriptionService ──────────────────────────────

    public function test_service_transitions_expired_to_grace(): void
    {
        // Term ended yesterday — subscription should move to grace
        $sub = $this->createActiveSubscription();

        $count = $this->service->transitionExpiredToGrace();

        $this->assertSame(1, $count);
        $sub->refresh();
        $this->assertSame(Subscription::STATUS_GRACE, $sub->status);
    }

    public function test_service_transitions_grace_to_locked(): void
    {
        $sub = $this->createGraceSubscription(daysLeft: -1); // grace already expired

        $count = $this->service->transitionGraceToLocked();

        $this->assertSame(1, $count);
        $sub->refresh();
        $this->assertSame(Subscription::STATUS_LOCKED, $sub->status);
    }

    public function test_service_does_not_double_transition(): void
    {
        $sub = $this->createActiveSubscription();

        $this->service->transitionExpiredToGrace();
        $count = $this->service->transitionExpiredToGrace(); // second run

        $this->assertSame(0, $count); // already in grace — not counted again
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createActiveSubscription(): Subscription
    {
        return Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $this->term->academic_year_id,
            'term_id'          => $this->term->id,
            'amount'           => 500,
            'start_date'       => now()->subDays(90)->toDateString(),
            'end_date'         => now()->subDay()->toDateString(), // expired
            'status'           => Subscription::STATUS_ACTIVE,
            'activated_at'     => now()->subDays(90),
        ]);
    }

    private function createGraceSubscription(int $daysLeft): Subscription
    {
        return Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $this->term->academic_year_id,
            'term_id'          => $this->term->id,
            'amount'           => 500,
            'start_date'       => now()->subDays(100)->toDateString(),
            'end_date'         => now()->subDays(10)->toDateString(),
            'grace_ends_at'    => now()->addDays($daysLeft),
            'status'           => Subscription::STATUS_GRACE,
            'activated_at'     => now()->subDays(100),
        ]);
    }

    private function createSubscriptionWithStatus(string $status): Subscription
    {
        return Subscription::create([
            'tenant_id'        => $this->tenant->id,
            'academic_year_id' => $this->term->academic_year_id,
            'term_id'          => $this->term->id,
            'amount'           => 500,
            'start_date'       => now()->subDays(30)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'status'           => $status,
        ]);
    }
}
