<?php

use App\Http\Controllers\Api\Agent\ApplyResultController;
use App\Http\Controllers\Api\Agent\ConfigController;
use App\Http\Controllers\Api\Agent\EventBatchController;
use App\Http\Controllers\Api\Agent\HeartbeatController;
use Illuminate\Support\Facades\Route;

Route::prefix('agent/v1')
    ->middleware(['agent.auth', 'throttle:agent'])
    ->group(function (): void {
        Route::post('heartbeat', [HeartbeatController::class, 'store'])->name('agent.heartbeat');
        Route::post('events', [EventBatchController::class, 'store'])->name('agent.events');
        Route::get('config', [ConfigController::class, 'show'])->name('agent.config');
        Route::post('apply-results', [ApplyResultController::class, 'store'])->name('agent.apply-results');
    });
