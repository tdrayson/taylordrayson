<?php

use App\Http\Controllers\Api\V1\FlightController;
use App\Http\Controllers\Api\V1\HealthExportController;
use App\Http\Controllers\Api\V1\NoteController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

// Moved into /api/v1 and behind the API token. Answering rather than 404ing
// means a phone still pointed at the old path says so in the response and the
// logs, instead of silently dropping a day of metrics.
Route::any('/health/ingest', fn (): JsonResponse => response()->json([
    'message' => 'Moved to POST /api/v1/health-export, with an Authorization: Bearer <token> header.',
], 410))->name('health.ingest.gone');

Route::prefix('v1')->middleware('api.token')->name('api.v1.')->group(function () {
    Route::get('/ping', fn (): JsonResponse => response()->json(['data' => ['ok' => true]]))->name('ping');
    Route::apiResource('notes', NoteController::class);
    Route::apiResource('flights', FlightController::class);

    // Ingest routes are named for the app that sends them, since the payload
    // shape is that app's contract rather than ours.
    Route::get('/health-export', [HealthExportController::class, 'ping'])->name('health-export.ping');
    Route::post('/health-export', [HealthExportController::class, 'store'])->name('health-export.store');
});
