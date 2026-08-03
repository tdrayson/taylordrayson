<?php

namespace App\Actions\Workouts;

use App\Data\SetgraphWorkout;
use App\Enums\Source;
use App\Models\Activity;
use Carbon\CarbonImmutable;

/**
 * Record a Setgraph share against the day's gym session.
 *
 * Setgraph normally arrives first: the phone shares the moment the workout
 * ends, while Strava has to upload and then wait for the next `strava:sync`.
 * So when no activity sits near the given time this creates one, sourced to
 * Setgraph and carrying no heart rate. `StravaSync` later finds that unclaimed
 * row and adopts it rather than creating a duplicate.
 *
 * When Strava did get there first, the sets are merged onto the existing
 * activity and nothing new is created.
 */
class RecordSetgraphWorkout
{
    /**
     * How far from the stated time to look for the same session. Setgraph
     * timestamps the share, not the first lift, so the gap is the length of
     * the workout rather than clock drift.
     */
    public const MATCH_WINDOW_MINUTES = 90;

    private const DEFAULT_TYPE = 'weight-training';

    private const DEFAULT_NAME = 'Weight Training';

    public function __construct(private ParseSetgraphWorkout $parse) {}

    /**
     * @return array{activity: Activity, workout: SetgraphWorkout, created: bool}
     */
    public function __invoke(string $text, CarbonImmutable $occurredAt, ?string $timezone = null): array
    {
        $workout = ($this->parse)($text);
        $activity = $this->matchingActivity($occurredAt);
        $created = $activity === null;

        if ($activity === null) {
            $activity = new Activity([
                'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
                'type' => self::DEFAULT_TYPE,
                'name' => self::DEFAULT_NAME,
                'duration' => $workout->duration,
                'source' => Source::Setgraph->value,
                'timezone' => $timezone ?? config('app.home_timezone'),
            ]);
        }

        // Duration only fills a hole: a matched Strava activity already knows
        // its real moving time, and Setgraph's is the rounded summary figure.
        if ($activity->duration === null && $workout->duration !== null) {
            $activity->duration = $workout->duration;
        }

        $activity->meta = [...$activity->meta ?? [], 'sets' => $workout->sets];
        $activity->save();

        return ['activity' => $activity, 'workout' => $workout, 'created' => $created];
    }

    /**
     * The gym session this share belongs to, whichever source logged it first.
     * Only strength activities are considered, so a walk that happens to bracket
     * the same window is never claimed.
     */
    private function matchingActivity(CarbonImmutable $occurredAt): ?Activity
    {
        // The window holds at most a handful of rows, so the closest one is
        // picked in PHP rather than with a driver-specific date expression.
        return Activity::query()
            ->whereIn('type', [self::DEFAULT_TYPE, 'workout'])
            ->whereBetween('occurred_at', [
                $occurredAt->subMinutes(self::MATCH_WINDOW_MINUTES)->format('Y-m-d H:i:s'),
                $occurredAt->addMinutes(self::MATCH_WINDOW_MINUTES)->format('Y-m-d H:i:s'),
            ])
            ->get()
            ->sortBy(fn (Activity $activity): int => abs($activity->occurred_at->diffInSeconds($occurredAt)))
            ->first();
    }
}
