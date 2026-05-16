<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TenantRegistrationController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Biometric\AdmsController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Webhooks\MoolreWebhookController;
use App\Http\Controllers\Webhooks\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check — reachable from any domain/IP (load balancers, monitors)
|--------------------------------------------------------------------------
| Returns 200 OK {"status":"ok"} when DB + cache + queue are healthy.
| Returns 503 {"status":"degraded"} if any check fails.
| Throttled to 60/min so it can't be used as an amplification vector.
*/
Route::get('/health', HealthCheckController::class)
    ->middleware('throttle:60,1')
    ->name('health');

/*
|--------------------------------------------------------------------------
| School Self-Registration — accessible on any domain (root or subdomain)
|--------------------------------------------------------------------------
*/
Route::get ('/register/school',                [TenantRegistrationController::class, 'showForm'])  ->name('register.school');
Route::post('/register/school',                [TenantRegistrationController::class, 'submit'])    ->name('register.school.submit')
    ->middleware('throttle:5,1');
Route::get ('/register/school/check-email',    [TenantRegistrationController::class, 'checkEmail'])->name('register.school.check-email');
Route::get ('/register/school/verify/{token}', [TenantRegistrationController::class, 'verify'])    ->name('register.school.verify')
    ->middleware('throttle:10,1');
Route::get ('/register/school/done',           [TenantRegistrationController::class, 'done'])      ->name('register.school.done');

/*
|--------------------------------------------------------------------------
| Super Admin Routes — superadmin.{domain}
|--------------------------------------------------------------------------
*/
Route::domain('superadmin.' . config('app.domain'))->group(function () {

    // ── Auth (guest only) ────────────────────────────────────────────────
    Route::middleware('guest')->group(function () {
        Route::get ('/login', [LoginController::class, 'showLogin'])->name('superadmin.login');
        Route::post('/login', [LoginController::class, 'login'])
            ->middleware('throttle:6,1')
            ->name('superadmin.login.submit');
    });

    Route::post('/logout', [LoginController::class, 'logout'])->name('superadmin.logout');

    // ── 2FA challenge (between login and protected routes) ───────────────
    Route::get ('/2fa/challenge', [TwoFactorController::class, 'showChallenge'])->name('superadmin.2fa.challenge');
    Route::post('/2fa/challenge', [TwoFactorController::class, 'challenge'])    ->name('superadmin.2fa.challenge.submit')->middleware('throttle:10,1');

    // ── Protected routes ─────────────────────────────────────────────────
    Route::middleware(['super.admin', '2fa'])->group(base_path('routes/superadmin.php'));
});

/*
|--------------------------------------------------------------------------
| School Admin Routes — {slug}.admin.{domain}
|--------------------------------------------------------------------------
*/
Route::domain('{slug}.admin.' . config('app.domain'))
    ->middleware(['resolve.tenant'])
    ->group(function () {

        // ── Auth (guest only) ────────────────────────────────────────────
        Route::middleware('guest')->group(function () {
            Route::get ('/login', [LoginController::class, 'showLogin'])->name('admin.login');
            Route::post('/login', [LoginController::class, 'login'])
                ->middleware('throttle:6,1')
                ->name('admin.login.submit');

            // Password reset — throttled to prevent enumeration + brute-force
            Route::get ('/forgot-password',         [PasswordResetController::class, 'showForgotForm'])->name('password.request');
            Route::post('/forgot-password',         [PasswordResetController::class, 'sendResetLink'])
                ->middleware('throttle:5,1')   // 5 link requests per minute per IP
                ->name('password.email');
            Route::get ('/reset-password/{token}',  [PasswordResetController::class, 'showResetForm'])->name('password.reset');
            Route::post('/reset-password',          [PasswordResetController::class, 'resetPassword'])
                ->middleware('throttle:5,1')   // 5 reset attempts per minute per IP
                ->name('password.update');
        });

        Route::post('/logout', [LoginController::class, 'logout'])->name('admin.logout');

        // ── 2FA challenge (between login and protected routes) ───────────
        Route::get ('/2fa/challenge', [TwoFactorController::class, 'showChallenge'])->name('admin.2fa.challenge');
        Route::post('/2fa/challenge', [TwoFactorController::class, 'challenge'])    ->name('admin.2fa.challenge.submit')->middleware('throttle:10,1');

        // ── Protected routes ─────────────────────────────────────────────
        Route::middleware(['school.admin', '2fa', 'subscription.admin'])
            ->group(base_path('routes/admin.php'));
    });

/*
|--------------------------------------------------------------------------
| Public School Website Routes — {slug}.{domain}
|--------------------------------------------------------------------------
*/
Route::domain('{slug}.' . config('app.domain'))
    ->middleware(['resolve.tenant', 'subscription.website'])
    ->group(base_path('routes/website.php'));

/*
|--------------------------------------------------------------------------
| Payment Routes — accessible regardless of subscription status
|--------------------------------------------------------------------------
*/
// Payment routes: throttled to prevent gateway API quota exhaustion by bots
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/pay/{slug}',      [PaymentController::class, 'initiate'])->name('payment.initiate');
    Route::get('/pay/{slug}/page', [PaymentController::class, 'page'])    ->name('payment.page');
});
Route::get('/payment/callback/paystack', [PaymentController::class, 'paystackCallback'])->name('payment.callback.paystack');
Route::get('/payment/callback/moolre',   [PaymentController::class, 'moolreCallback'])  ->name('payment.callback.moolre');

/*
|--------------------------------------------------------------------------
| Webhook Routes — no CSRF, signature verified inside controller
|--------------------------------------------------------------------------
*/
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->prefix('webhooks')
    ->group(function () {
        Route::post('/paystack', [PaystackWebhookController::class, 'handle'])
            ->name('webhook.paystack');
        Route::post('/moolre',   [MoolreWebhookController::class, '__invoke'])
            ->name('webhook.moolre');
    });

/*
|--------------------------------------------------------------------------
| ZKTeco ADMS Endpoint — device pushes attendance here (no CSRF, no auth)
|--------------------------------------------------------------------------
| Configure on device: Server IP = this server, Path = /biometric/adms
| Supports ZKTeco ADMS v2 protocol (getrequest + cdata push)
*/
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->prefix('biometric')
    ->group(function () {
        Route::get ('/adms', [AdmsController::class, 'get']) ->name('biometric.adms.get');
        Route::post('/adms', [AdmsController::class, 'post'])->name('biometric.adms.post');
    });
