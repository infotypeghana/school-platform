<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs every inbound HTTP request to the `request_logs` table.
 *
 * Design principles:
 *
 * 1. ZERO RESPONSE LATENCY — The DB write happens in terminate(), which
 *    is called by the kernel AFTER the response is dispatched to the browser.
 *    The end-user never waits for this write.
 *
 * 2. NEVER BREAKS A REQUEST — All DB writes are wrapped in try/catch.
 *    A failure writes to the file log but does not throw or impact the response.
 *
 * 3. TENANT-AWARE — Reads app('currentTenant') which ResolveTenantMiddleware
 *    or SetTenantFromToken has already bound to the IoC container.
 *
 * 4. REQUEST-ID TRACING — Injects a UUID `X-Request-ID` response header and
 *    binds the same UUID to the IoC container as 'request.id'. Any code that
 *    logs to audit_logs, payment_ledger, or the file log can reference the
 *    same ID for cross-system tracing.
 *
 * 5. PATH FILTERING — Skips health checks, Horizon dashboard, storage
 *    downloads, and static asset paths (no signal value, high volume).
 *
 * 6. PII SAFETY — Stores path and IP only. Query strings (may contain tokens
 *    or PII) are NOT stored. User-agent is stored (non-PII telemetry).
 */
class LogRequestMiddleware
{
    /**
     * Paths that should never be logged (high-volume / no signal).
     * Matched as prefix or exact.
     */
    private const SKIP_PREFIXES = [
        '/health',
        '/up',
        '/horizon',
        '/storage/signed',
        '/_debugbar',
        '/favicon',
        '/build/',
        '/vendor/',
    ];

    /**
     * File extensions that indicate static assets — skip logging.
     */
    private const SKIP_EXTENSIONS = [
        'js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'map', 'webp', 'avif',
    ];

    // ── Middleware lifecycle ───────────────────────────────────────────────────

    /**
     * Generate a request ID and bind it; record start time; inject response header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate and bind request ID immediately — available to all downstream code
        $requestId = (string) Str::uuid();
        app()->instance('request.id', $requestId);

        // Record start time with microsecond precision
        $request->attributes->set('_log_start', hrtime(true));

        $response = $next($request);

        // Inject tracing header into response — useful for support/debugging
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    /**
     * Write the log entry AFTER the response is sent to the browser.
     * Called by the HTTP kernel when the middleware implements TerminableMiddleware.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Skip non-signal paths
        if ($this->shouldSkip($request)) {
            return;
        }

        try {
            $startNs  = $request->attributes->get('_log_start', hrtime(true));
            $duration = (hrtime(true) - $startNs) / 1_000_000; // nanoseconds → milliseconds

            $tenant   = app('currentTenant');
            $user     = Auth::user();
            $userType = $this->resolveUserType($request, $user);

            RequestLog::record([
                'request_id'  => app()->bound('request.id') ? app('request.id') : (string) Str::uuid(),
                'tenant_id'   => $tenant?->id,
                'user_id'     => $user?->id,
                'user_type'   => $userType,
                'method'      => $request->method(),
                'path'        => '/' . ltrim($request->path(), '/'),
                'route_name'  => $request->route()?->getName(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => round($duration, 2),
                'ip_address'  => $request->ip() ?? '0.0.0.0',
                'user_agent'  => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            // NEVER let a logging failure break the application.
            // Silently write to file log and move on.
            Log::warning('[REQUEST_LOG] Failed to write request log entry', [
                'error'  => $e->getMessage(),
                'path'   => $request->path(),
                'method' => $request->method(),
            ]);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Determine whether this request should be skipped entirely.
     */
    private function shouldSkip(Request $request): bool
    {
        // Skip in unit test environment — avoids DB writes during testing
        if (app()->runningUnitTests()) {
            return true;
        }

        $path = '/' . ltrim($request->path(), '/');

        // Skip known high-volume / no-signal prefixes
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        // Skip static assets by file extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension && in_array($extension, self::SKIP_EXTENSIONS, true)) {
            return true;
        }

        return false;
    }

    /**
     * Infer the user session type from request context.
     *
     * Order of precedence:
     *   1. Sanctum API token → api
     *   2. Authenticated Laravel user role
     *   3. Teacher portal session key
     *   4. Parent portal session key
     *   5. Unauthenticated
     */
    private function resolveUserType(Request $request, mixed $user): string
    {
        // Sanctum-authenticated API request
        if ($request->bearerToken() || $request->is('api/*')) {
            if ($user) {
                return RequestLog::TYPE_API;
            }
        }

        // Laravel Auth user
        if ($user) {
            return match ($user->role) {
                'super_admin'  => RequestLog::TYPE_SUPER_ADMIN,
                'school_admin' => RequestLog::TYPE_SCHOOL_ADMIN,
                default        => RequestLog::TYPE_SCHOOL_ADMIN,
            };
        }

        // Teacher portal (session-based, no Laravel Auth)
        if ($request->session()->has('teacher_portal_id')) {
            return RequestLog::TYPE_TEACHER_PORTAL;
        }

        // Parent portal (session-based)
        if ($request->session()->has('parent_portal_student_id')) {
            return RequestLog::TYPE_PARENT_PORTAL;
        }

        return RequestLog::TYPE_UNAUTHENTICATED;
    }
}
