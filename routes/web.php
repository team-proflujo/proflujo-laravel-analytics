<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Proflujo\Analytics\Http\Controllers\AssetController;
use Proflujo\Analytics\Http\Controllers\ChartDataController;
use Proflujo\Analytics\Http\Controllers\DashboardController;
use Proflujo\Analytics\Http\Controllers\EventDataController;

Route::prefix(trim((string) config('analytics.dashboard.path'), '/'))
    ->middleware(config('analytics.dashboard.middleware'))
    ->name('analytics.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/events', EventDataController::class)->name('events');
        Route::get('/charts', ChartDataController::class)->name('charts');
        Route::get('/assets/{file}', AssetController::class)->where('file', '[A-Za-z0-9._-]+')->name('asset');
    });
