<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workouts\RecordSetgraphWorkout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkoutRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Take a workout shared from Setgraph as its raw share-sheet text and record it
 * against the matching activity. The client sends everything it has; picking
 * out the sets is this end's job.
 */
class WorkoutController extends Controller
{
    public function __invoke(StoreWorkoutRequest $request, RecordSetgraphWorkout $record): JsonResponse
    {
        $validated = $request->validated();

        $result = $record(
            $validated['text'],
            isset($validated['occurred_at'])
                ? CarbonImmutable::parse($validated['occurred_at'])
                : CarbonImmutable::now(),
            $validated['timezone'] ?? null,
        );

        $activity = $result['activity'];

        return response()->json([
            'data' => [
                'id' => $activity->id,
                'occurred_at' => $activity->occurred_at->format('Y-m-d H:i:s'),
                'type' => $activity->type,
                'name' => $activity->name,
                'sets' => count($result['workout']->sets),
                'exercises' => count(array_unique(array_column($result['workout']->sets, 'exercise'))),
                'volume_kg' => $result['workout']->volume(),
                // Tells the Shortcut whether this made a placeholder for Strava
                // to adopt, or landed on an activity that had already synced.
                'created' => $result['created'],
            ],
        ], $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
