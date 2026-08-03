<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workouts\RecordSetgraphWorkout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSetgraphWorkoutRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Take a workout shared from Setgraph as its raw share-sheet text and record it
 * against the matching activity. The client sends everything it has; picking
 * out the sets is this end's job.
 */
class SetgraphController extends Controller
{
    public function __invoke(StoreSetgraphWorkoutRequest $request, RecordSetgraphWorkout $record): JsonResponse
    {
        $validated = $request->validated();
        $timezone = $validated['timezone'] ?? config('app.home_timezone');

        $result = $record(
            $validated['text'],
            $this->wallClock(
                isset($validated['occurred_at'])
                    ? CarbonImmutable::parse($validated['occurred_at'])
                    : CarbonImmutable::now($timezone),
            ),
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

    /**
     * Keep the wall-clock digits and drop the offset.
     *
     * Shortcuts sends a native ISO 8601 date, so `occurred_at` arrives as
     * `2026-07-27T20:10:00+01:00`. Activities store local wall-clock time, not
     * an instant, so an offset-aware value would compare wrongly against them:
     * shared at 20:10 from New York it sits nine hours from a session logged at
     * 20:10, far outside the match window, and would duplicate the activity.
     * Reading the digits as UTC makes every comparison wall-clock to
     * wall-clock, the same trick StravaSync uses on `start_date_local`.
     */
    private function wallClock(CarbonImmutable $moment): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $moment->format('Y-m-d H:i:s'), 'UTC');
    }
}
