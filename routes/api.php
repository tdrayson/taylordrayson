<?php

use App\Http\Controllers\Api\V1\FlightController;
use App\Http\Controllers\Api\V1\HealthExport\ActivityRingsController;
use App\Http\Controllers\Api\V1\HealthExport\HeartRateController;
use App\Http\Controllers\Api\V1\HealthExport\SleepController;
use App\Http\Controllers\Api\V1\KindleController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\NowStateController;
use App\Http\Controllers\Api\V1\SetgraphController;
use App\Http\Controllers\Api\V1\TagController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.token')->name('api.v1.')->group(function () {
    Route::get('/ping', fn (): JsonResponse => response()->json(['data' => ['ok' => true]]))->name('ping');
    Route::apiResource('notes', NoteController::class);
    Route::apiResource('flights', FlightController::class);
    Route::get('/tags', TagController::class)->name('tags.index');

    // Ingest routes are named for the app that sends them, since the payload
    // shape is that app's contract rather than ours. One path per domain: each
    // takes only the metrics it knows and rejects the rest, so an automation
    // pointed at the wrong one says so on the phone.
    Route::prefix('health-export')->name('health-export.')->group(function () {
        Route::get('/sleep', [SleepController::class, 'show'])->name('sleep.show');
        Route::post('/sleep', [SleepController::class, 'store'])->name('sleep.store');

        Route::get('/heart-rate', [HeartRateController::class, 'show'])->name('heart-rate.show');
        Route::post('/heart-rate', [HeartRateController::class, 'store'])->name('heart-rate.store');

        Route::get('/activity-rings', [ActivityRingsController::class, 'show'])->name('activity-rings.show');
        Route::post('/activity-rings', [ActivityRingsController::class, 'store'])->name('activity-rings.store');
    });

    Route::post('/setgraph', SetgraphController::class)->name('setgraph.store');
    Route::post('/kindle', KindleController::class)->name('kindle.store');

    // Ambient readings from Apple Shortcuts for the Now page. Unlike the ingest
    // routes above, the payload shape is ours, so the path is named for what it
    // holds rather than for the app that sends it.
    Route::get('/now', [NowStateController::class, 'show'])->name('now.show');
    Route::post('/now', [NowStateController::class, 'store'])->name('now.store');
});
