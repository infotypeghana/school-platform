<?php

use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\SuperAdmin\PackageController;
use App\Http\Controllers\SuperAdmin\PaymentController;
use App\Http\Controllers\SuperAdmin\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', fn () => view('superadmin.dashboard'))->name('superadmin.dashboard');
Route::resource('/tenants',        \App\Http\Controllers\SuperAdmin\TenantController::class)
    ->names('superadmin.tenants');
Route::resource('/academic-years', \App\Http\Controllers\SuperAdmin\AcademicYearController::class)
    ->names('superadmin.academic-years');
Route::resource('/academic-terms', \App\Http\Controllers\SuperAdmin\AcademicTermController::class)
    ->names('superadmin.academic-terms');

// ── Subscriptions ─────────────────────────────────────────────────────────────
Route::resource('/subscriptions', SubscriptionController::class)
    ->names([
        'index'  => 'superadmin.subscriptions',
        'show'   => 'superadmin.subscriptions.show',
        'edit'   => 'superadmin.subscriptions.edit',
        'update' => 'superadmin.subscriptions.update',
    ])
    ->only(['index', 'show', 'edit', 'update']);

Route::post('/subscriptions/{subscription}/transition',   [SubscriptionController::class, 'transition'])
    ->name('superadmin.subscriptions.transition');
Route::post('/subscriptions/{subscription}/extend-grace', [SubscriptionController::class, 'extendGrace'])
    ->name('superadmin.subscriptions.extend-grace');

Route::get('/payments', [PaymentController::class, 'index'])->name('superadmin.payments');

// ── Subscription Packages ─────────────────────────────────────────────────────
Route::resource('/packages', PackageController::class)
    ->names([
        'index'   => 'superadmin.packages',
        'create'  => 'superadmin.packages.create',
        'store'   => 'superadmin.packages.store',
        'edit'    => 'superadmin.packages.edit',
        'update'  => 'superadmin.packages.update',
        'destroy' => 'superadmin.packages.destroy',
    ])
    ->except(['show']);

// ── Two-Factor Authentication ─────────────────────────────────────────────────
Route::get ('/settings/2fa',         [TwoFactorController::class, 'showSetup'])    ->name('superadmin.2fa.setup');
Route::post('/settings/2fa/confirm', [TwoFactorController::class, 'confirmSetup']) ->name('superadmin.2fa.confirm');
Route::post('/settings/2fa/disable', [TwoFactorController::class, 'disable'])      ->name('superadmin.2fa.disable');
