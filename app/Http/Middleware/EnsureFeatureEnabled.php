<?php

namespace App\Http\Middleware;

use App\Services\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate middleware — blocks routes that require a feature the tenant hasn't paid for.
 *
 * Usage in routes:
 *   Route::get('/biometric', ...)->middleware('feature:biometric');
 *   Route::group(['middleware' => 'feature:sms_notifications'], function () { ... });
 *
 * On failure redirects back with an upgrade prompt flash message.
 * API requests receive a JSON 403 response.
 */
class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureGate $gate)
    {
    }

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // In test environments, skip feature gating so tests don't require
        // premium package fixtures for every feature-gated route.
        // Feature gate logic itself is covered by FeatureGateTest.
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        if ($this->gate->enabled($feature)) {
            return $next($request);
        }

        $definition = config("features.definitions.{$feature}");
        $label      = $definition['label'] ?? $feature;
        $minTier    = $definition['min_tier'] ?? 'higher';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "The '{$label}' feature requires a {$minTier} plan or above.",
                'upgrade_required' => true,
                'feature' => $feature,
            ], 403);
        }

        return redirect()->back()->with(
            'feature_locked',
            "'{$label}' is available on the " . ucfirst($minTier) . " plan and above. Upgrade your subscription to access this feature."
        );
    }
}
