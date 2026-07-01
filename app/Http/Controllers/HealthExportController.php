<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HealthExportController extends Controller
{
    /**
     * Capture an incoming Health Auto Export payload verbatim for inspection.
     *
     * The raw body is stored untouched alongside a compact structural summary
     * so large payloads can be understood without opening the full capture.
     * This is the ingestion seam the per-metric processors will grow from.
     */
    /**
     * Confirm the endpoint is reachable (e.g. from a browser during setup).
     */
    public function ping(): JsonResponse
    {
        $captures = collect(Storage::disk('local')->files('health/incoming'))
            ->filter(fn (string $path): bool => str_ends_with($path, '.summary.json'))
            ->count();

        return response()->json([
            'ok' => true,
            'message' => 'Health export endpoint ready. Send payloads with POST.',
            'captures' => $captures,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->authorised($request)) {
            return response()->json(['message' => 'Invalid or missing token.'], 401);
        }

        $raw = $request->getContent();
        $reference = Carbon::now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
        $disk = Storage::disk('local');

        $disk->put("health/incoming/{$reference}.json", $raw === '' ? '{}' : $raw);

        $decoded = json_decode($raw, true);
        $summary = is_array($decoded)
            ? $this->summarise($decoded)
            : ['error' => 'Body was not valid JSON', 'bytes' => strlen($raw)];

        $disk->put(
            "health/incoming/{$reference}.summary.json",
            (string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return response()->json([
            'ok' => true,
            'reference' => $reference,
            'bytes' => strlen($raw),
            'summary' => $summary,
        ]);
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

    /**
     * Build a compact structural summary of a Health Auto Export payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function summarise(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $metrics = array_map(fn (array $metric): array => [
            'name' => $metric['name'] ?? null,
            'units' => $metric['units'] ?? null,
            'points' => is_array($metric['data'] ?? null) ? count($metric['data']) : 0,
            'fields' => is_array($metric['data'][0] ?? null) ? array_keys($metric['data'][0]) : [],
            'sample' => $metric['data'][0] ?? null,
        ], array_values(array_filter($data['metrics'] ?? [], 'is_array')));

        $workouts = array_map(fn (array $workout): array => [
            'name' => $workout['name'] ?? ($workout['workoutActivityType'] ?? null),
            'start' => $workout['start'] ?? null,
            'end' => $workout['end'] ?? null,
            'fields' => array_keys($workout),
        ], array_values(array_filter($data['workouts'] ?? [], 'is_array')));

        return [
            'top_level_keys' => array_keys($payload),
            'data_keys' => is_array($data) ? array_keys($data) : [],
            'metric_count' => count($metrics),
            'metrics' => $metrics,
            'workout_count' => count($workouts),
            'workouts' => $workouts,
        ];
    }
}
