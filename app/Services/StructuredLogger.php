<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Structured logger that enriches every log entry with:
 *   tenant_id, user_id, role, ip, user_agent, action, timestamp
 *
 * Usage:
 *   app(StructuredLogger::class)->info('student.created', ['student_id' => $id]);
 *   app(StructuredLogger::class)->warning('fee.overdue', ['fee_id' => $id, 'days' => 5]);
 *   app(StructuredLogger::class)->security('login.failed', ['email' => $email]);
 */
class StructuredLogger
{
    public function info(string $action, array $context = []): void
    {
        Log::channel('stack')->info($action, $this->enrich($action, $context));
    }

    public function warning(string $action, array $context = []): void
    {
        Log::channel('stack')->warning($action, $this->enrich($action, $context));
    }

    public function error(string $action, array $context = []): void
    {
        Log::channel('stack')->error($action, $this->enrich($action, $context));
    }

    public function security(string $action, array $context = []): void
    {
        Log::channel('stack')->warning('[SECURITY] ' . $action, $this->enrich($action, $context, true));
    }

    public function audit(string $action, array $context = []): void
    {
        Log::channel('stack')->info('[AUDIT] ' . $action, $this->enrich($action, $context));
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function enrich(string $action, array $context, bool $isSecurity = false): array
    {
        $user   = Auth::user();
        $tenant = app('currentTenant');
        $req    = Request::instance();

        return array_merge([
            'action'     => $action,
            'tenant_id'  => $tenant?->id,
            'tenant'     => $tenant?->slug,
            'user_id'    => $user?->id,
            'user_email' => $isSecurity ? ($user?->email ?? $context['email'] ?? null) : null,
            'role'       => $user?->role,
            'ip'         => $req?->ip(),
            'user_agent' => $req?->userAgent(),
            'timestamp'  => now()->toIso8601String(),
        ], $context);
    }
}
