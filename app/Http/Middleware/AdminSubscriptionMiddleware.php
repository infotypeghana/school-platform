<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSubscriptionMiddleware
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        $status = $this->subscriptionService->getStatus($tenant);

        if (in_array($status, ['trial', 'active'])) {
            return $next($request);
        }

        if ($status === 'grace') {
            $subscription = $this->subscriptionService->getCurrentSubscription($tenant);
            view()->share('graceSubscription', $subscription);
            view()->share('graceDaysRemaining', $subscription?->graceDaysRemaining() ?? 0);
            return $next($request);
        }

        // locked / suspended / none → redirect to admin lock screen
        $subscription = $this->subscriptionService->getCurrentSubscription($tenant);
        return response()->view('admin.lock', [
            'tenant'       => $tenant,
            'subscription' => $subscription,
        ], 403);
    }
}
