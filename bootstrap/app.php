<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'resolve.tenant'       => \App\Http\Middleware\ResolveTenantMiddleware::class,
            'subscription.admin'   => \App\Http\Middleware\AdminSubscriptionMiddleware::class,
            'subscription.website' => \App\Http\Middleware\WebsiteSubscriptionMiddleware::class,
            'school.admin'         => \App\Http\Middleware\EnsureSchoolAdmin::class,
            'super.admin'          => \App\Http\Middleware\EnsureSuperAdmin::class,
            'teacher.portal'       => \App\Http\Middleware\EnsureTeacherPortalAuth::class,
            '2fa'                  => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'api.tenant'           => \App\Http\Middleware\SetTenantFromToken::class,
            'feature'              => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);

        // Ensure HTTPS cookies work correctly in production
        $middleware->trustProxies(at: '*');

        // Append security response headers on every request
        $middleware->append(\App\Http\Middleware\SecureHeadersMiddleware::class);

        // Global request telemetry — terminate() fires AFTER response dispatch,
        // so it adds zero latency. Writes to request_logs for analytics + tracing.
        // Also injects X-Request-ID response header for distributed tracing.
        $middleware->append(\App\Http\Middleware\LogRequestMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
