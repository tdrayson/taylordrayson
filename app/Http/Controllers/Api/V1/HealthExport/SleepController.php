<?php

namespace App\Http\Controllers\Api\V1\HealthExport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HealthExport\StoreSleepRequest;
use App\Jobs\ProcessHealthExport;
use App\Support\Health\SleepProcessor;
use Illuminate\Http\JsonResponse;

/**
 * Take a `sleep_analysis` send from Health Auto Export. A night arrives as many
 * segments, so this only acknowledges receipt and {@see ProcessHealthExport}
 * aggregates and scores it.
 */
class SleepController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'ok' => true,
            'accepts' => (new StoreSleepRequest)->metrics(),
            'message' => 'Sleep endpoint ready. Send these metrics with POST.',
        ]]);
    }

    public function store(StoreSleepRequest $request): JsonResponse
    {
        ProcessHealthExport::dispatch($request->payload(), SleepProcessor::class);

        return response()->json(['data' => [
            'ok' => true,
            'queued' => true,
            'received' => $request->received(),
        ]]);
    }
}
