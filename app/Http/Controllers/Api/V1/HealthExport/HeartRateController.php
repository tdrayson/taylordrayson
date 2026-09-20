<?php

namespace App\Http\Controllers\Api\V1\HealthExport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HealthExport\StoreHeartRateRequest;
use App\Jobs\ProcessHealthExport;
use App\Support\Health\HeartRateProcessor;
use Illuminate\Http\JsonResponse;

/**
 * Take a `heart_rate` send from Health Auto Export. Matching samples to
 * activities walks every activity, so it is left to {@see ProcessHealthExport}.
 */
class HeartRateController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'ok' => true,
            'accepts' => (new StoreHeartRateRequest)->metrics(),
            'message' => 'Heart rate endpoint ready. Send these metrics with POST.',
        ]]);
    }

    public function store(StoreHeartRateRequest $request): JsonResponse
    {
        ProcessHealthExport::dispatch($request->payload(), HeartRateProcessor::class);

        return response()->json(['data' => [
            'ok' => true,
            'queued' => true,
            'received' => $request->received(),
        ]]);
    }
}
