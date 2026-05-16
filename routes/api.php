<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\TimetableController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SchoolMS REST API — v1
|--------------------------------------------------------------------------
|
| Base path: /api/v1
|
| Authentication: Laravel Sanctum token (Authorization: Bearer {token})
|
| Tenant isolation: SetTenantFromToken middleware binds `currentTenant`
| from the authenticated user's tenant_id so HasTenantScope works
| identically to web routes.
|
*/

// ── API Documentation (Swagger UI) — publicly accessible ─────────────────────
Route::get('/docs', fn () => view('api.docs'))->name('api.docs');

Route::prefix('v1')->group(function () {

    // ── Public ────────────────────────────────────────────────────────────────
    // Throttle login to 10 attempts per minute to prevent credential stuffing.
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    });

    // ── Authenticated ─────────────────────────────────────────────────────────
    // 60 requests per minute per token — generous for a mobile app, firm against abuse.
    // feature:api_access — only standard+ plans get REST API access
    Route::middleware(['auth:sanctum', 'api.tenant', 'throttle:60,1', 'feature:api_access'])->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::delete('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
            Route::get('/me',        [AuthController::class, 'me'])    ->name('api.auth.me');
        });

        // Dashboard
        Route::get('/dashboard', DashboardController::class)->name('api.dashboard');

        // Students
        Route::get('/students',       [StudentController::class, 'index'])->name('api.students.index');
        Route::get('/students/{id}',  [StudentController::class, 'show']) ->name('api.students.show')
            ->where('id', '[0-9]+');

        // Classes
        Route::get('/classes',        [ClassController::class, 'index'])->name('api.classes.index');
        Route::get('/classes/{id}',   [ClassController::class, 'show']) ->name('api.classes.show')
            ->where('id', '[0-9]+');

        // Attendance
        Route::get ('/attendance', [AttendanceController::class, 'index'])->name('api.attendance.index');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('api.attendance.store');

        // Announcements
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('api.announcements.index');

        // Timetable
        Route::get('/timetable', [TimetableController::class, 'index'])->name('api.timetable.index');

        // Assessments
        Route::get('/assessments', [AssessmentController::class, 'index'])->name('api.assessments.index');
    });
});
