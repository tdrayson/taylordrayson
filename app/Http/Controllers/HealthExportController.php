<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessHealthExport;
use App\Support\Health\HealthPayloadSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HealthExportController extends Controller
{
    /**
     * Confirm the endpoint is reachable (e.g. from a browser during setup).
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => 'Health export endpoint ready. Send payloads with POST.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->authorised($request)) {
            return response()->json(['message' => 'Invalid or missing token.'], 401);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Body was not a JSON object.'], 422);
        }

        Log::info('health.ingest received', HealthPayloadSummary::for($payload));

        ProcessHealthExport::dispatch($payload);

        return response()->json(['ok' => true]);
    }

    private function authorised(Request $request): bool
    {
        $expected = config('services.health_export.token');

        if (! $expected) {
            return true;
        }

        $provided = $request->bearerToken() ?? $request->input('token');

        return is_string($provided) && hash_equals((string) $expected, $provided);
    }
}
