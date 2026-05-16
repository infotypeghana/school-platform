<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * GET /health
 *
 * Used by load balancers, uptime monitors, and container liveness probes.
 * Returns 200 when all critical services are reachable, or 503 with a
 * JSON body listing what failed so ops can diagnose quickly.
 */
class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $allOk  = true;

        $checks['database'] = $this->checkDatabase();
        $checks['cache']    = $this->checkCache();
        $checks['queue']    = $this->checkQueue();
        $checks['storage']  = $this->checkStorage();
        $checks['horizon']  = $this->checkHorizon();
        $checks['saas']     = $this->checkSaasMetrics();  // non-critical; informational

        foreach (['database', 'cache', 'queue', 'storage'] as $critical) {
            if (($checks[$critical]['status'] ?? 'fail') !== 'ok') {
                $allOk = false;
            }
        }

        return response()->json([
            'status'    => $allOk ? 'ok' : 'degraded',
            'checks'    => $checks,
            'app_env'   => app()->environment(),
            'timestamp' => now()->toIso8601ZuluString(),
        ], $allOk ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $start = hrtime(true);
            DB::select('SELECT 1');
            $ms = (int) round((hrtime(true) - $start) / 1_000_000);
            return ['status' => 'ok', 'latency_ms' => $ms, 'driver' => config('database.default')];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = '_health_' . uniqid();
            Cache::put($key, 'ping', 5);
            $ok = Cache::get($key) === 'ping';
            Cache::forget($key);
            return $ok
                ? ['status' => 'ok', 'driver' => config('cache.default')]
                : ['status' => 'fail', 'error' => 'Cache round-trip mismatch'];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            return [
                'status'      => 'ok',
                'connection'  => config('queue.default'),
                'queue_sizes' => [
                    'notifications' => Queue::size('notifications'),
                    'sms'           => Queue::size('sms'),
                    'default'       => Queue::size('default'),
                    'pdf'           => Queue::size('pdf'),
                    'exports'       => Queue::size('exports'),
                ],
            ];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $path = storage_path('framework/cache/.health_probe');
            file_put_contents($path, time());
            $ok = file_exists($path);
            @unlink($path);
            return $ok ? ['status' => 'ok'] : ['status' => 'fail', 'error' => 'Storage not writable'];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkHorizon(): array
    {
        try {
            $prefix = config('horizon.prefix', 'laravel_horizon:');
            $key    = $prefix . 'master_supervisor';
            $alive  = Cache::store('redis')->has($key);
            return $alive
                ? ['status' => 'ok']
                : ['status' => 'warn', 'note' => 'Horizon heartbeat absent — worker may be stopped'];
        } catch (Throwable) {
            return ['status' => 'warn', 'note' => 'Could not read Horizon heartbeat from Redis'];
        }
    }

    private function checkSaasMetrics(): array
    {
        try {
            return [
                'status'               => 'ok',
                'active_tenants'       => Tenant::whereIn('status', ['trial', 'active', 'grace'])->count(),
                'active_subscriptions' => Subscription::whereIn('status', ['trial', 'active'])->count(),
                'grace_subscriptions'  => Subscription::where('status', 'grace')->count(),
                'locked_tenants'       => Tenant::where('status', 'locked')->count(),
            ];
        } catch (Throwable $e) {
            return ['status' => 'warn', 'error' => $e->getMessage()];
        }
    }
}
