<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\IpBlockController;
use App\Http\Controllers\IpProtectionController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('nodes', [NodeController::class, 'index'])->name('nodes.index');
    Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
    Route::get('blocks', [IpBlockController::class, 'index'])->name('blocks.index');
    Route::get('protections', [IpProtectionController::class, 'index'])->name('protections.index');
    Route::get('policies', [PolicyController::class, 'index'])->name('policies.index');
    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

    Route::middleware('admin')->group(function (): void {
        Route::post('nodes', [NodeController::class, 'store'])->name('nodes.store');
        Route::patch('nodes/{node}', [NodeController::class, 'update'])->name('nodes.update');
        Route::post('nodes/{node}/token', [NodeController::class, 'rotateToken'])->name('nodes.token');
        Route::post('nodes/{node}/agent-config', [NodeController::class, 'agentConfig'])->name('nodes.agent-config');
        Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
        Route::patch('sites/{site}', [SiteController::class, 'update'])->name('sites.update');
        Route::post('blocks', [IpBlockController::class, 'store'])->name('blocks.store');
        Route::post('blocks/{block}/revoke', [IpBlockController::class, 'destroy'])->name('blocks.revoke');
        Route::post('protections', [IpProtectionController::class, 'store'])->name('protections.store');
        Route::delete('protections/{protection}', [IpProtectionController::class, 'destroy'])->name('protections.destroy');
        Route::post('policies', [PolicyController::class, 'update'])->name('policies.update');
        Route::post('policies/{revision}/rollback', [PolicyController::class, 'rollback'])->name('policies.rollback');
    });
});
