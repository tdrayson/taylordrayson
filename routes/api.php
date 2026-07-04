<?php

use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\HealthExportController;
use Illuminate\Support\Facades\Route;

Route::get('/health/ingest', [HealthExportController::class, 'ping'])
    ->name('health.ping');

Route::post('/health/ingest', [HealthExportController::class, 'store'])
    ->name('health.ingest');

Route::prefix('v1')->middleware('api.token')->name('api.v1.')->group(function () {
    Route::get('/ping', fn () => response()->json(['data' => ['ok' => true]]))->name('ping');
    Route::apiResource('notes', NoteController::class);
});
