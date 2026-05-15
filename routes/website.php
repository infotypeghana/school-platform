<?php

use App\Http\Controllers\Website\ParentPortalController;
use App\Http\Controllers\Website\TeacherPortalController;
use App\Http\Controllers\Website\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::get('/',           [WebsiteController::class, 'home'])       ->name('website.home');
Route::get('/about',      [WebsiteController::class, 'about'])      ->name('website.about');
Route::get('/academics',  [WebsiteController::class, 'academics'])  ->name('website.academics');
Route::get('/news',       [WebsiteController::class, 'news'])       ->name('website.news');
Route::get('/gallery',    [WebsiteController::class, 'gallery'])    ->name('website.gallery');
Route::get('/contact',    [WebsiteController::class, 'contact'])    ->name('website.contact');
Route::get('/admissions', [WebsiteController::class, 'admissions']) ->name('website.admissions');
Route::post('/admissions',[WebsiteController::class, 'applyAdmission'])->name('website.admissions.apply');

// ── Parent Portal — session-based auth, multi-ward ───────────────────────────
Route::get ('/portal',                [ParentPortalController::class, 'index'])        ->name('website.portal');
Route::post('/portal',                [ParentPortalController::class, 'lookup'])       ->name('website.portal.lookup')
    ->middleware('throttle:6,1');
Route::get ('/portal/dashboard',      [ParentPortalController::class, 'dashboard'])    ->name('website.portal.dashboard');
Route::post('/portal/switch-ward',    [ParentPortalController::class, 'switchWard'])   ->name('website.portal.switch-ward');
Route::get ('/portal/add-ward',       [ParentPortalController::class, 'addWardForm'])  ->name('website.portal.add-ward');
Route::post('/portal/logout',         [ParentPortalController::class, 'logout'])       ->name('website.portal.logout');

// ── Teacher Portal ───────────────────────────────────────────────────────────
Route::prefix('teacher')->group(function () {
    Route::get ('/login',  [TeacherPortalController::class, 'showLogin'])->name('teacher.portal.login');
    Route::post('/login',  [TeacherPortalController::class, 'login'])   ->name('teacher.portal.login.submit')
        ->middleware('throttle:6,1');
    Route::post('/logout', [TeacherPortalController::class, 'logout'])  ->name('teacher.portal.logout');

    Route::middleware('teacher.portal')->group(function () {
        Route::get('/dashboard',     [TeacherPortalController::class, 'dashboard'])    ->name('teacher.portal.dashboard');
        Route::get('/scores',        [TeacherPortalController::class, 'scores'])       ->name('teacher.portal.scores');
        Route::get('/scores/edit',   [TeacherPortalController::class, 'scoresEdit'])   ->name('teacher.portal.scores.edit');
        Route::put('/scores',        [TeacherPortalController::class, 'scoresUpdate']) ->name('teacher.portal.scores.update');
        Route::get('/timetable',     [TeacherPortalController::class, 'timetable'])    ->name('teacher.portal.timetable');
        // Remarks — class teachers only
        Route::get('/remarks',       [TeacherPortalController::class, 'remarksIndex']) ->name('teacher.portal.remarks');
        Route::get('/remarks/edit',  [TeacherPortalController::class, 'remarksEdit'])  ->name('teacher.portal.remarks.edit');
        Route::put('/remarks',       [TeacherPortalController::class, 'remarksUpdate'])->name('teacher.portal.remarks.update');
    });
});

// Simple contact form handler (stores to log / sends notification)
Route::post('/contact', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'name'    => 'required|string|max:150',
        'contact' => 'required|string|max:150',
        'subject' => 'required|string|max:200',
        'message' => 'required|string|max:2000',
    ]);

    \Illuminate\Support\Facades\Log::info('Website contact form submission', [
        'tenant'  => app('currentTenant')?->slug,
        'name'    => $request->name,
        'contact' => $request->contact,
        'subject' => $request->subject,
    ]);

    return back()->with('contact_sent', true);
})->name('website.contact.send');
