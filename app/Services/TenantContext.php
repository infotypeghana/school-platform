<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Central TenantContext — the single source of truth for the current tenant.
 *
 * This singleton wraps the IoC `app('currentTenant')` binding with:
 *  - Strict access enforcement (throws if accessed when no tenant is bound)
 *  - Bypass audit logging (every withoutTenantScope call is recorded)
 *  - Helper methods for safe tenant-aware code
 *
 * Usage:
 *   app(TenantContext::class)->tenant()         → current Tenant (throws if null)
 *   app(TenantContext::class)->id()             → int tenant ID (throws if null)
 *   app(TenantContext::class)->tenantOrNull()   → ?Tenant (safe, no throw)
 *   app(TenantContext::class)->isSuperAdmin()   → bool (true when no tenant = super admin context)
 *   app(TenantContext::class)->recordBypass($reason) → log a scope bypass
 */
class TenantContext
{
    /** Total bypass count for this request (exposed for observability). */
    private int $bypassCount = 0;

    /** All bypass reasons recorded this request. */
    private array $bypassLog = [];

    // ── Tenant access ──────────────────────────────────────────────────────────

    /**
     * Get the current tenant — throws if none is bound.
     * Use this in controller/service code that MUST have a tenant.
     */
    public function tenant(): Tenant
    {
        $tenant = app('currentTenant');

        if (! $tenant instanceof Tenant) {
            throw new \RuntimeException(
                'TenantContext: attempted to access current tenant but none is bound. ' .
                'Ensure ResolveTenantMiddleware has run before this code is reached.'
            );
        }

        return $tenant;
    }

    /**
     * Get the current tenant ID — throws if none is bound.
     */
    public function id(): int
    {
        return $this->tenant()->id;
    }

    /**
     * Get the current tenant or null (safe — for code that runs in both contexts).
     */
    public function tenantOrNull(): ?Tenant
    {
        $tenant = app('currentTenant');
        return $tenant instanceof Tenant ? $tenant : null;
    }

    /**
     * Returns true when running in super-admin context (no tenant bound).
     * Use to guard code that is legitimately tenant-free.
     */
    public function isSuperAdmin(): bool
    {
        return ! (app('currentTenant') instanceof Tenant);
    }

    /**
     * Is a tenant currently bound?
     */
    public function hasTenant(): bool
    {
        return app('currentTenant') instanceof Tenant;
    }

    // ── Bypass tracking ────────────────────────────────────────────────────────

    /**
     * Record that tenant scope is being intentionally bypassed.
     * Called whenever withoutTenantScope() or withoutGlobalScopes() is used.
     * Always logs at INFO level so the bypass is auditable.
     *
     * @param  string  $reason   Why the bypass is needed
     * @param  string  $context  Calling class/method
     */
    public function recordBypass(string $reason, string $context = ''): void
    {
        $this->bypassCount++;
        $entry = [
            'bypass_count' => $this->bypassCount,
            'reason'       => $reason,
            'context'      => $context ?: $this->callerContext(),
            'tenant_id'    => $this->tenantOrNull()?->id,
            'is_super_admin' => $this->isSuperAdmin(),
        ];
        $this->bypassLog[] = $entry;

        Log::info('[TENANT_BYPASS] Tenant scope bypassed', $entry);
    }

    /**
     * Returns all bypasses recorded this request.
     */
    public function bypasses(): array
    {
        return $this->bypassLog;
    }

    public function bypassCount(): int
    {
        return $this->bypassCount;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Execute a callable with guaranteed tenant context.
     * Throws if no tenant is bound.
     */
    public function withTenant(callable $callback): mixed
    {
        $this->tenant(); // throws if no tenant
        return $callback($this->tenant());
    }

    /**
     * Execute a callable only when in super-admin context (no tenant).
     * Logs an audit entry if called from a tenant context.
     */
    public function asSuperAdmin(callable $callback, string $reason = 'super_admin_operation'): mixed
    {
        if ($this->hasTenant()) {
            $this->recordBypass($reason);
        }
        return $callback();
    }

    // ── Private ────────────────────────────────────────────────────────────────

    private function callerContext(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';
            if ($class && ! str_contains($class, 'TenantContext')) {
                return ($frame['class'] ?? '') . '::' . ($frame['function'] ?? '');
            }
        }
        return 'unknown';
    }
}
