<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Central feature-gating service for per-tenant plan entitlements.
 *
 * Usage:
 *   app(FeatureGate::class)->enabled('sms_notifications')   // bool
 *   app(FeatureGate::class)->for($tenant)->enabled('biometric')
 *   @feature('sms_notifications')  ... @endfeature  (Blade directive)
 *
 * The gate resolves the current tenant from the IoC container when
 * no explicit tenant is passed, making controller/view usage seamless.
 */
class FeatureGate
{
    private ?Tenant $tenant = null;

    public function __construct(private readonly SubscriptionService $subscriptionService)
    {
    }

    /** Scope the gate to a specific tenant (fluent API). */
    public function for(Tenant $tenant): static
    {
        $clone = clone $this;
        $clone->tenant = $tenant;
        return $clone;
    }

    /**
     * Check whether a feature key is enabled for the current/given tenant.
     * Always returns true for super admins (no tenant context).
     */
    public function enabled(string $feature): bool
    {
        $tenant = $this->resolveTenant();

        // No tenant = super admin context — all features enabled
        if (! $tenant) {
            return true;
        }

        return Cache::remember(
            "feature:{$tenant->id}:{$feature}",
            now()->addMinutes(10),
            fn () => $this->check($tenant, $feature)
        );
    }

    /** Inverse of enabled(). */
    public function disabled(string $feature): bool
    {
        return ! $this->enabled($feature);
    }

    /**
     * Abort with 403 if the feature is not enabled.
     * Use in controllers: $this->gate->require('biometric');
     */
    public function require(string $feature): void
    {
        abort_unless($this->enabled($feature), 403, "Feature '{$feature}' is not available on your current plan.");
    }

    /** Get the storage limit (MB) for the current tenant's plan tier. */
    public function storageLimit(): int
    {
        $tier = $this->resolveTier($this->resolveTenant());
        return config("features.storage_limits.{$tier}", 500);
    }

    /** Get the student limit for the current tenant's plan tier (0 = unlimited). */
    public function studentLimit(): int
    {
        $tier = $this->resolveTier($this->resolveTenant());
        return config("features.student_limits.{$tier}", 500);
    }

    /** Get the SMS limit for the current tenant's plan tier (0 = unlimited). */
    public function smsLimit(): int
    {
        $tier = $this->resolveTier($this->resolveTenant());
        return config("features.sms_limits.{$tier}", 0);
    }

    /** Flush all cached feature checks for a tenant. */
    public function flushCache(Tenant $tenant): void
    {
        $features = array_keys(config('features.definitions', []));
        foreach ($features as $feature) {
            Cache::forget("feature:{$tenant->id}:{$feature}");
        }
        Cache::forget("feature_tier:{$tenant->id}");
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function check(Tenant $tenant, string $feature): bool
    {
        $definition = config("features.definitions.{$feature}");

        // Unknown feature keys are always disabled
        if (! $definition) {
            return false;
        }

        $tenantTier  = $this->resolveTier($tenant);
        $requiredTier = $definition['min_tier'];

        $tierValues = config('features.tiers', []);

        $tenantTierValue   = $tierValues[$tenantTier]   ?? 0;
        $requiredTierValue = $tierValues[$requiredTier] ?? 0;

        return $tenantTierValue >= $requiredTierValue;
    }

    private function resolveTier(?Tenant $tenant): string
    {
        if (! $tenant) {
            return 'enterprise'; // super admin
        }

        return Cache::remember(
            "feature_tier:{$tenant->id}",
            now()->addMinutes(10),
            fn () => $this->fetchTier($tenant)
        );
    }

    private function fetchTier(Tenant $tenant): string
    {
        $subscription = $this->subscriptionService->getCurrentSubscription($tenant);

        if (! $subscription) {
            return 'trial';
        }

        // Trial subscriptions
        if ($subscription->is_trial || $subscription->status === Subscription::STATUS_TRIAL) {
            return 'trial';
        }

        // Locked/suspended tenants fall back to basic feature set
        if ($subscription->isLocked()) {
            return 'basic';
        }

        // Resolve tier from package slug
        $package = $subscription->package;
        if (! $package) {
            return 'basic';
        }

        return $this->packageSlugToTier($package->slug);
    }

    private function packageSlugToTier(string $slug): string
    {
        // Map package slugs to tier names.
        // Flexible — works with any slug convention.
        $slug = strtolower($slug);

        if (str_contains($slug, 'enterprise')) return 'enterprise';
        if (str_contains($slug, 'premium'))    return 'premium';
        if (str_contains($slug, 'standard') || str_contains($slug, 'growth')) return 'standard';
        if (str_contains($slug, 'basic')    || str_contains($slug, 'starter')) return 'basic';

        return 'basic'; // safe default
    }

    private function resolveTenant(): ?Tenant
    {
        return $this->tenant ?? app('currentTenant');
    }
}
