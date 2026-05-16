<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\ReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SaaS metrics dashboard for the super admin panel.
 * Shows revenue, tenant health, usage stats, and system metrics.
 */
class SaasMetricsController extends Controller
{
    public function __construct(private readonly ReconciliationService $reconciliation)
    {
    }

    public function index(Request $request)
    {
        $period = $request->get('period', 'month'); // month | quarter | year

        [$from, $to] = match ($period) {
            'quarter' => [Carbon::now()->startOfQuarter(), Carbon::now()],
            'year'    => [Carbon::now()->startOfYear(),    Carbon::now()],
            default   => [Carbon::now()->startOfMonth(),   Carbon::now()],
        };

        $metrics = Cache::remember(
            "saas:metrics:{$period}",
            now()->addMinutes(10),
            fn () => $this->buildMetrics($from, $to)
        );

        return view('superadmin.metrics.index', compact('metrics', 'period', 'from', 'to'));
    }

    public function tenantUsage(Tenant $tenant)
    {
        $usage = Cache::remember(
            "saas:tenant_usage:{$tenant->id}",
            now()->addMinutes(5),
            fn () => $this->buildTenantUsage($tenant)
        );

        return view('superadmin.metrics.tenant', compact('tenant', 'usage'));
    }

    // ── Private builders ──────────────────────────────────────────────────────

    private function buildMetrics(Carbon $from, Carbon $to): array
    {
        // Revenue
        $revenue = (float) Payment::where('status', 'success')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $revenueByMonth = Payment::where('status', 'success')
            ->whereBetween('created_at', [$from, $to])
            ->select(DB::raw('YEAR(created_at) y, MONTH(created_at) m, SUM(amount) total'))
            ->groupBy('y', 'm')
            ->orderBy('y')->orderBy('m')
            ->get()
            ->map(fn ($r) => ['label' => "{$r->y}-{$r->m}", 'total' => (float) $r->total]);

        // Tenant counts
        $totalTenants    = Tenant::count();
        $activeTenants   = Tenant::whereIn('status', ['active', 'trial'])->count();
        $graceTenants    = Tenant::where('status', 'grace')->count();
        $lockedTenants   = Tenant::where('status', 'locked')->count();

        // New sign-ups in period
        $newTenants = Tenant::whereBetween('created_at', [$from, $to])->count();

        // Subscription breakdown
        $subBreakdown = Subscription::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Total students across all active tenants
        $totalStudents = Student::withoutGlobalScopes()->where('status', 'active')->count();

        // Gateway split
        $gatewayBreakdown = Payment::where('status', 'success')
            ->whereBetween('created_at', [$from, $to])
            ->select('gateway', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('gateway')
            ->get();

        // Top paying tenants
        $topTenants = Payment::where('status', 'success')
            ->whereBetween('created_at', [$from, $to])
            ->select('tenant_id', DB::raw('SUM(amount) as total'))
            ->groupBy('tenant_id')
            ->orderByDesc('total')
            ->limit(10)
            ->with('tenant:id,name,slug,status')
            ->get();

        // Reconciliation summary
        $reconciliation = $this->reconciliation->reconcile($from, $to);

        return compact(
            'revenue',
            'revenueByMonth',
            'totalTenants',
            'activeTenants',
            'graceTenants',
            'lockedTenants',
            'newTenants',
            'subBreakdown',
            'totalStudents',
            'gatewayBreakdown',
            'topTenants',
            'reconciliation',
        );
    }

    private function buildTenantUsage(Tenant $tenant): array
    {
        // Must use withoutTenantScope since we're in super admin context (no tenant bound)
        $studentCount = Student::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->count();

        $totalPayments = Payment::where('tenant_id', $tenant->id)
            ->where('status', 'success')
            ->sum('amount');

        $lastPayment = Payment::where('tenant_id', $tenant->id)
            ->where('status', 'success')
            ->latest()
            ->first();

        $currentSub = $tenant->currentSubscription();

        $recentActivity = DB::table('audit_logs')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return compact(
            'studentCount',
            'totalPayments',
            'lastPayment',
            'currentSub',
            'recentActivity',
        );
    }
}
