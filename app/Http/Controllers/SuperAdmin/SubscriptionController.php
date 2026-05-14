<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateSubscriptionRequest;
use App\Models\AcademicTerm;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $subs = Subscription::with(['tenant', 'term.academicYear'])
            ->when($request->search, fn ($q, $s) =>
                $q->whereHas('tenant', fn ($q) => $q->where('name', 'like', "%{$s}%"))
            )
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('superadmin.subscriptions.index', compact('subs'));
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Subscription $subscription): View
    {
        $subscription->load(['tenant', 'term.academicYear', 'payments']);
        return view('superadmin.subscriptions.show', compact('subscription'));
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(Subscription $subscription): View
    {
        $terms = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $plans = config('billing.plans', []);

        return view('superadmin.subscriptions.edit', compact('subscription', 'terms', 'plans'));
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $subscription->update($request->validated());

        $this->subscriptionService->flushCache($subscription->tenant);

        return redirect()
            ->route('superadmin.subscriptions.show', $subscription)
            ->with('success', 'Subscription saved.');
    }

    // ── Manual Status Transition ──────────────────────────────────────────────

    /**
     * Manually transition a subscription to a different status.
     * Supports: activate, grace, lock, suspend.
     */
    public function transition(Request $request, Subscription $subscription): RedirectResponse
    {
        $request->validate([
            'action' => ['required', 'in:activate,grace,lock,suspend'],
        ]);

        match ($request->action) {
            'activate' => $subscription->transitionToActive(),
            'grace'    => $subscription->transitionToGrace(),
            'lock'     => $subscription->transitionToLocked(),
            'suspend'  => (function () use ($subscription) {
                $subscription->update(['status' => Subscription::STATUS_SUSPENDED]);
                $subscription->tenant?->update(['status' => Subscription::STATUS_SUSPENDED]);
            })(),
        };

        $this->subscriptionService->flushCache($subscription->tenant);

        return back()->with('success', 'Subscription transitioned to ' . $request->action . '.');
    }

    // ── Extend Grace ──────────────────────────────────────────────────────────

    /**
     * Add N days to the current grace end date (or from now if not in grace).
     */
    public function extendGrace(Request $request, Subscription $subscription): RedirectResponse
    {
        $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $base = $subscription->grace_ends_at
            ? $subscription->grace_ends_at->copy()
            : now();

        $subscription->update([
            'status'        => Subscription::STATUS_GRACE,
            'grace_ends_at' => $base->addDays((int) $request->days),
        ]);
        $subscription->tenant?->update(['status' => Subscription::STATUS_GRACE]);

        $this->subscriptionService->flushCache($subscription->tenant);

        return back()->with('success', "Grace period extended by {$request->days} day(s).");
    }
}
