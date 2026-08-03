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
     * When the workout began, worked back from the share.
     *
     * Setgraph shares once the session is over, so the raw timestamp is the end.
     * Taking the stated length off it lands near the real start, which matters
     * for more than tidiness: measured from the end, a two-hour session sits
     * further from its Strava start than the match window allows and would
     * duplicate the activity. With no stated length the share time is all we
     * have, and Strava corrects it on the next sync anyway.
     */
    private function estimatedStart(CarbonImmutable $sharedAt, SetgraphWorkout $workout): CarbonImmutable
    {
        return $workout->duration === null
            ? $sharedAt
            : $sharedAt->subSeconds($workout->duration);
    }

    /**
     * The gym session this share belongs to, whichever source logged it first.
     *
     * Overlapping clock time decides it, not proximity. Racquet sports and
     * crossfit all map to the generic `workout` type, so an afternoon of tennis
     * followed by an evening in the gym puts two candidates in any sensible
     * window; only one of them actually runs at the same time as the sets being
     * recorded. Where several overlap, the longest shared stretch wins.
     *
     * A share with no stated length has no range to compare, so it falls back
     * to the nearest start, and only against `weight-training`. That keeps the
     * tennis session out of it at the cost of missing a gym session Strava
     * happened to record as a generic workout, which the next sync then adopts.
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
