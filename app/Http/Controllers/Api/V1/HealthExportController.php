<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessHealthExport;
use App\Support\Health\HealthPayloadSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Take a payload from the Health Auto Export app. The path is named for the app
 * because the payload shape is that app's contract, not ours. Metrics arrive in
 * bulk, so this only acknowledges receipt and {@see ProcessHealthExport} does
 * the work.
 */
class HealthExportController extends Controller
{
    /**
     * Confirm the endpoint is reachable and the token is accepted, e.g. from
     * the phone while setting the export up.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'data' => [
                'ok' => true,
                'message' => 'Health export endpoint ready. Send payloads with POST.',
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Body was not a JSON object.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $summary = HealthPayloadSummary::for($payload);

        Log::info('health export received', $summary);

        ProcessHealthExport::dispatch($payload);

        // Echoing the metric summary back makes a misconfigured export obvious
        // from the phone: an empty list means nothing was actually sent.
        return response()->json(['data' => ['ok' => true, 'metrics' => $summary['metrics'] ?? []]]);
    }
}
