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
        ]);

        // Ensure HTTPS cookies work correctly in production
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
