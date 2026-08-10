<?php

namespace App\Actions\Workouts;

use App\Data\SetgraphWorkout;
use App\Enums\Source;
use App\Models\Activity;
use Carbon\CarbonImmutable;

/**
 * Record a Setgraph share against the day's gym session, merging onto an existing
 * activity or creating one when Setgraph got there first. `StravaSync` later
 * adopts the unclaimed row rather than duplicating it.
 */
class RecordSetgraphWorkout
{
    /**
     * How far from the estimated start to look for the same session. Covers
     * the delay between finishing and hitting share, plus the difference
     * between Setgraph's rounded length and Strava's.
     */
    public const MATCH_WINDOW_MINUTES = 90;

    private const DEFAULT_TYPE = 'weight-training';

    private const DEFAULT_NAME = 'Weight Training';

    public function __construct(private ParseSetgraphWorkout $parse) {}

    /**
     * @param  CarbonImmutable  $sharedAt  When the share was sent, which is the end of the
     *                                     workout rather than its start.
     * @return array{activity: Activity, workout: SetgraphWorkout, created: bool}
     */
    public function __invoke(string $text, CarbonImmutable $sharedAt, ?string $timezone = null): array
    {
        $workout = ($this->parse)($text);
        $occurredAt = $this->estimatedStart($sharedAt, $workout);
        $activity = $this->matchingActivity($occurredAt, $workout->duration);
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
     * When the workout began, worked back from the share, which Setgraph sends at
     * the end. Without this a long session sits outside the match window and
     * duplicates its Strava activity.
     */
    private function estimatedStart(CarbonImmutable $sharedAt, SetgraphWorkout $workout): CarbonImmutable
    {
        return $workout->duration === null
            ? $sharedAt
            : $sharedAt->subSeconds($workout->duration);
    }

    /**
     * The gym session this share belongs to, decided by overlapping clock time
     * rather than proximity, since racquet sports and crossfit share the generic
     * `workout` type. A share with no stated length has no range to compare, so it
     * falls back to the nearest `weight-training` start.
     */
    private function matchingActivity(CarbonImmutable $occurredAt, ?int $duration): ?Activity
    {
        $end = $occurredAt->addSeconds($duration ?? 0);

        // The window holds at most a handful of rows, so the winner is picked in
        // PHP rather than with a driver-specific date expression.
        $candidates = Activity::query()
            ->whereIn('type', [self::DEFAULT_TYPE, 'workout'])
            ->whereBetween('occurred_at', [
                $occurredAt->subMinutes(self::MATCH_WINDOW_MINUTES)->format('Y-m-d H:i:s'),
                $end->addMinutes(self::MATCH_WINDOW_MINUTES)->format('Y-m-d H:i:s'),
            ])
            ->get();

        if ($duration === null) {
            return $candidates
                ->where('type', self::DEFAULT_TYPE)
                ->sortBy(fn (Activity $activity): int => abs($activity->occurred_at->diffInSeconds($occurredAt)))
                ->first();
        }

        return $candidates
            ->map(fn (Activity $activity): array => [
                'activity' => $activity,
                'overlap' => $this->overlapSeconds($activity, $occurredAt, $end),
            ])
            ->filter(fn (array $candidate): bool => $candidate['overlap'] > 0)
            ->sortByDesc('overlap')
            ->first()['activity'] ?? null;
    }

    /**
     * How long an existing activity runs at the same time as [$start, $end].
     * An activity with no recorded length counts as an instant.
     */
    private function overlapSeconds(Activity $activity, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $activityStart = CarbonImmutable::parse($activity->occurred_at->format('Y-m-d H:i:s'), 'UTC');
        $activityEnd = $activityStart->addSeconds($activity->duration ?? 0);

        $overlapStart = $start->max($activityStart);
        $overlapEnd = $end->min($activityEnd);

        return max(0, $overlapEnd->getTimestamp() - $overlapStart->getTimestamp());
    }
}
