<?php

namespace App\Providers;

use App\Models\Admission;
use App\Models\Assessment;
use App\Models\Fee;
use App\Models\FeedingFee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Observers\AuditObserver;
use App\Policies\AdmissionPolicy;
use App\Policies\FeePolicy;
use App\Policies\StudentPolicy;
use App\Policies\TeacherPolicy;
use App\Services\GradeCalculator;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
    }

    public function boot(): void
    {
        // ── Audit observers ───────────────────────────────────────────────────
        // Core academic records — every create/update/delete is logged
        Student::observe(AuditObserver::class);
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
    }
}
