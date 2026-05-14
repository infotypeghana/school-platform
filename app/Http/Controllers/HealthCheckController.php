<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * GET /health
 *
 * Used by load balancers, uptime monitors, and container liveness probes.
 *
 * Returns 200 when all critical services are reachable, or 503 with a
 * JSON body listing what failed so ops can diagnose quickly.
 *
 * Response shape:
 *   {
 *     "status": "ok" | "degraded",
 *     "checks": {
 *       "database": { "status": "ok" | "fail", "latency_ms": 4 },
 *       "cache":    { "status": "ok" | "fail" },
 *       "queue":    { "status": "ok" | "fail" }
 *     },
 *     "app_env":  "production",
 *     "timestamp": "2026-05-14T06:00:00Z"
 *   }
 */
class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $allOk  = true;

        // ── Database ──────────────────────────────────────────────────────────
        $checks['database'] = $this->checkDatabase();
        if ($checks['database']['status'] !== 'ok') {
            $allOk = false;
        }

        // ── Cache ─────────────────────────────────────────────────────────────
        $checks['cache'] = $this->checkCache();
        if ($checks['cache']['status'] !== 'ok') {
            $allOk = false;
        }

        // ── Queue ─────────────────────────────────────────────────────────────
        $checks['queue'] = $this->checkQueue();
        if ($checks['queue']['status'] !== 'ok') {
            $allOk = false;
        }

        $status  = $allOk ? 'ok' : 'degraded';
        $httpCode = $allOk ? 200 : 503;

        return response()->json([
            'status'    => $status,
            'checks'    => $checks,
            'app_env'   => app()->environment(),
            'timestamp' => now()->toIso8601ZuluString(),
        ], $httpCode);
    }

    // ── Individual checks ─────────────────────────────────────────────────────

    private function checkDatabase(): array
    {
        try {
            $start = hrtime(true);
            DB::select('SELECT 1');
            $latency = (int) round((hrtime(true) - $start) / 1_000_000); // ms

            return ['status' => 'ok', 'latency_ms' => $latency];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = '_health_check_' . str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);
            Cache::put($key, 'ping', 5);
            $ok = Cache::get($key) === 'ping';
            Cache::forget($key);

            return $ok
                ? ['status' => 'ok']
                : ['status' => 'fail', 'error' => 'Cache round-trip failed'];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            // Just verify the queue connection is reachable — don't dispatch a real job.
            // For Redis: pings the connection; for database: runs a tiny query.
            $size = Queue::size('default');

            return ['status' => 'ok', 'default_queue_size' => $size];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }
}
