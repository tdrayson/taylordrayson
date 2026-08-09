<?php

use App\Http\Controllers\Api\V1\FlightController;
use App\Http\Controllers\Api\V1\HealthExportController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\SetgraphController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.token')->name('api.v1.')->group(function () {
    Route::get('/ping', fn (): JsonResponse => response()->json(['data' => ['ok' => true]]))->name('ping');
    Route::apiResource('notes', NoteController::class);
    Route::apiResource('flights', FlightController::class);

    // Ingest routes are named for the app that sends them, since the payload
    // shape is that app's contract rather than ours.
    Route::get('/health-export', [HealthExportController::class, 'ping'])->name('health-export.ping');
    Route::post('/health-export', [HealthExportController::class, 'store'])->name('health-export.store');
    Route::post('/setgraph', SetgraphController::class)->name('setgraph.store');
});
