<?php

namespace App\Providers;

use App\Models\Admission;
use App\Models\Assessment;
use App\Models\Fee;
use App\Models\FeedingFee;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Observers\AuditObserver;
use App\Observers\PaymentObserver;
use App\Policies\AdmissionPolicy;
use App\Policies\FeePolicy;
use App\Policies\StudentPolicy;
use App\Policies\TeacherPolicy;
use App\Services\FeatureGate;
use App\Services\GradeCalculator;
use App\Services\StructuredLogger;
use App\Services\SubscriptionService;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind SubscriptionService as a singleton — shared state is fine
        // since the tenant context lives per-request via IoC
        $this->app->singleton(SubscriptionService::class);

        // GradeCalculator is stateless, can be shared
        $this->app->singleton(GradeCalculator::class);

        // FeatureGate — singleton per request; tenant resolved lazily from IoC
        $this->app->singleton(FeatureGate::class);

        // StructuredLogger — singleton for consistent contextual logging
        $this->app->singleton(StructuredLogger::class);

        // TenantContext — singleton wrapping app('currentTenant') with audit/enforcement
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        // ── Audit observers ───────────────────────────────────────────────────
        // Core academic records — every create/update/delete is logged
        Student::observe(AuditObserver::class);
        // Payment ledger — immutable audit trail for every payment state change
        Payment::observe(PaymentObserver::class);
        Teacher::observe(AuditObserver::class);
        Fee::observe(AuditObserver::class);
        Assessment::observe(AuditObserver::class);
        // Feeding fees
        FeedingFee::observe(AuditObserver::class);
        // Enrolment pipeline + class/subject structure
        Admission::observe(AuditObserver::class);
        SchoolClass::observe(AuditObserver::class);
        Subject::observe(AuditObserver::class);

        // ── Authorization policies ────────────────────────────────────────────
        Gate::policy(Student::class,   StudentPolicy::class);
        Gate::policy(Teacher::class,   TeacherPolicy::class);
        Gate::policy(Admission::class, AdmissionPolicy::class);
        Gate::policy(Fee::class,       FeePolicy::class);

        // ── Super-admin gate: bypass all policy checks ────────────────────────
        Gate::before(function ($user) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });

        // Strict model behaviour — catch lazy loading, mass-assignment issues early
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // Force HTTPS in production
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        // ── Horizon access gate ───────────────────────────────────────────────
        // Restricts /horizon dashboard to authenticated super admins in production.
        // In local/staging, anyone can access it (no auth configured for dev convenience).
        Horizon::auth(function ($request) {
            if (app()->environment('local', 'testing')) {
                return true;
            }
            return $request->user()?->role === 'super_admin';
        });

        // Register a null current tenant as default so IoC always has a binding.
        // ResolveTenantMiddleware will override this per request.
        if (! $this->app->bound('currentTenant')) {
            $this->app->instance('currentTenant', null);
        }

        // ── Blade directives for feature gating ──────────────────────────────
        // @feature('sms_notifications') ... @endfeature
        Blade::if('feature', function (string $feature): bool {
            return app(FeatureGate::class)->enabled($feature);
        });

        // ── Plan-aware API rate limiter ───────────────────────────────────────
        // Replaces the blanket 'throttle:60,1' with per-plan limits.
        // trial/basic → 30 req/min; standard → 60; premium → 120; enterprise → 300
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            if (! $user) {
                return Limit::perMinute(30)->by($request->ip());
            }

            // Resolve tier from the bound tenant's active subscription package
            $tenant   = app('currentTenant');
            $tier     = 'basic';

            if ($tenant) {
                $slug  = optional(optional($tenant->activeSubscription)->package)->slug ?? '';
                $tier  = match (true) {
                    str_contains($slug, 'enterprise') => 'enterprise',
                    str_contains($slug, 'premium')    => 'premium',
                    str_contains($slug, 'standard')   => 'standard',
                    default                           => 'basic',
                };
            }

            $limit = match ($tier) {
                'enterprise' => 300,
                'premium'    => 120,
                'standard'   => 60,
                default      => 30,   // trial + basic
            };

            return Limit::perMinute($limit)->by($user->id . '|' . ($tenant?->id ?? 'global'));
        });

        // ── Query safety guard (dev mode) ─────────────────────────────────────
        // Detects queries against tenant-scoped tables that omit tenant_id.
        // Logs at WARNING level in dev; no-op in production (performance safe).
        if (! app()->isProduction()) {
            $this->installQuerySafetyGuard();
        }
    }

    /**
     * Install a DB query listener that warns when tenant-scoped tables
     * are queried without a tenant_id predicate.
     *
     * This is a DEVELOPMENT SAFETY NET — it catches accidental unscoped
     * queries before they reach production. False-positive rate is low
     * because HasTenantScope auto-injects the predicate for all scoped models.
     */
    private function installQuerySafetyGuard(): void
    {
        // Tables that MUST always be filtered by tenant_id
        static $tenantTables = [
            'students', 'teachers', 'school_classes', 'subjects',
            'attendances', 'assessments', 'report_cards', 'fees',
            'admissions', 'timetables', 'announcements', 'expenses',
            'biometric_devices', 'biometric_enrollments', 'biometric_logs',
            'school_exports', 'feeding_configs', 'feeding_payments',
            'feeding_fees', 'lesson_notes', 'lesson_note_attachments',
            'curriculum_strands', 'curriculum_sub_strands',
            'schemes_of_work', 'scheme_of_work_weeks', 'promotions',
        ];

        DB::listen(function (QueryExecuted $event) use ($tenantTables): void {
            $sql = strtolower($event->sql);

            foreach ($tenantTables as $table) {
                // Query hits this tenant-scoped table
                if (! str_contains($sql, "`{$table}`") && ! str_contains($sql, " {$table} ") && ! str_contains($sql, " {$table}\n")) {
                    continue;
                }

                // Check if tenant_id predicate is present
                if (! str_contains($sql, 'tenant_id')) {
                    // Allowlist: schema queries (show columns, etc.)
                    if (str_starts_with(trim($sql), 'select column_name') ||
                        str_starts_with(trim($sql), 'pragma') ||
                        str_contains($sql, 'information_schema')) {
                        continue;
                    }

                    Log::warning('[QUERY_SAFETY] Unscoped query on tenant table', [
                        'table'      => $table,
                        'sql'        => $event->sql,
                        'time_ms'    => $event->time,
                        'tenant_id'  => app('currentTenant')?->id,
                    ]);
                }
            }
        });
    }
}
