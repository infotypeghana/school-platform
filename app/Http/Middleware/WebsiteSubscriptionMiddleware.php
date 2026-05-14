<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebsiteSubscriptionMiddleware
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
            // Inject grace banner data into every public page view
            view()->share('showGraceBanner', true);
            view()->share('graceSubscription', $subscription);
            view()->share('graceDaysRemaining', $subscription?->graceDaysRemaining() ?? 0);
            view()->share('graceIsUrgent', $subscription?->graceIsUrgent() ?? false);
            return $next($request);
        }

        // locked / suspended / none → replace entire website with lock screen
        $subscription = $this->subscriptionService->getCurrentSubscription($tenant);
        return response()->view('website.lock', [
            'tenant'       => $tenant,
            'subscription' => $subscription,
        ], 503); // 503 Service Unavailable — correct status for a locked/suspended site
    }
}
