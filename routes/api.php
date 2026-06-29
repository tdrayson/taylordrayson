<?php

use App\Http\Controllers\HealthExportController;
use Illuminate\Support\Facades\Route;

Route::get('/health/ingest', [HealthExportController::class, 'ping'])
    ->name('health.ping');

Route::post('/health/ingest', [HealthExportController::class, 'store'])
    ->name('health.ingest');
