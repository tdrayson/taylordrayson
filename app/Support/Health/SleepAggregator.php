<?php

namespace App\Support\Health;

use App\Enums\Source;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SleepAggregator
{
    /**
     * Apple sleep-stage label to the matching Sleep column.
     *
     * @var array<string, string>
     */
    private const STAGE_COLUMN = [
        'Awake' => 'awake',
        'REM' => 'rem',
        'Core' => 'core',
        'Deep' => 'deep',
        // Unstaged sleep (older watchOS / "In Bed + Asleep" mode) counts as light.
        'Asleep' => 'core',
    ];

    /**
     * Source preference when more than one device reports the same night.
     *
     * @var list<string>
     */
    private const SOURCE_PRIORITY = [Source::Oura->value, Source::AppleWatch->value];

    /**
     * A no-data gap (seconds) that separates one sleep session from the next.
     * Bridges in-bed awake periods but splits a daytime nap or stray reading
     * away from the night sleep.
     */
    private const SESSION_GAP = 10800;

    /**
     * Reduce raw `sleep_analysis` segments into one record per night, choosing a
     * single source per night by preference. "In Bed" segments are treated as
     * the sleep-window envelope, never as a stage.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @return array<string, array<string, mixed>> Records keyed by night date (Y-m-d).
     */
    public function aggregate(array $segments): array
    {
        $grouped = [];

        foreach ($segments as $segment) {
            $value = $segment['value'] ?? null;
            $start = $segment['start'] ?? null;
            $end = $segment['end'] ?? null;

            if (! is_string($value) || ! is_string($start) || ! is_string($end)) {
                continue;
            }

            $source = $this->normaliseSource((string) ($segment['source'] ?? ''));
            $night = $this->nightFor($start);

            $grouped[$night][$source][] = [
                'value' => $value,
                'start' => $this->local($start),
                'end' => $this->local($end),
            ];
        }

        $records = [];

        foreach ($grouped as $night => $bySource) {
            $source = $this->preferredSource(array_keys($bySource));

            if ($source === null) {
                continue;
            }

            $records[$night] = $this->buildRecord($night, $source, $bySource[$source]);
        }

        ksort($records);

        return $records;
    }

    /**
     * Re-derive a single already-stored row from its persisted `stages`,
     * applying the current session logic. Used to retro-fix rows whose
     * duration was inflated by an older merge. Stage labels are lowercase
     * (`core`/`deep`/`rem`/`awake`) and carry no "In Bed" envelope.
     *
     * @param  array<int, array{stage: string, start: string, end: string}>  $storedStages
     * @return array<string, mixed>
     */
    public function resplitRow(string $night, string $source, array $storedStages): array
    {
        $labels = ['awake' => 'Awake', 'rem' => 'REM', 'core' => 'Core', 'deep' => 'Deep'];

        $segments = [];

        foreach ($storedStages as $stage) {
            $value = $labels[$stage['stage'] ?? ''] ?? null;

            if ($value === null || ! isset($stage['start'], $stage['end'])) {
                continue;
            }

            $segments[] = ['value' => $value, 'start' => $stage['start'], 'end' => $stage['end']];
        }

        return $this->buildRecord($night, $source, $segments);
    }

    /**
     * @param  array<int, array{value: string, start: string, end: string}>  $segments
     * @return array<string, mixed>
     */
    private function buildRecord(string $night, string $source, array $segments): array
    {
        $segments = $this->primarySession($segments);

        $stageSeconds = ['awake' => 0, 'rem' => 0, 'core' => 0, 'deep' => 0];
        $stages = [];
        $envelope = [];

        foreach ($segments as $segment) {
            if ($segment['value'] === 'In Bed') {
                $envelope[] = $segment;

                continue;
            }

            $column = self::STAGE_COLUMN[$segment['value']] ?? null;

            if ($column === null) {
                continue;
            }

            $stageSeconds[$column] += $this->seconds($segment['start'], $segment['end']);
            $stages[] = ['stage' => $column, 'start' => $segment['start'], 'end' => $segment['end']];
        }

        usort($stages, fn (array $first, array $second): int => strcmp($first['start'], $second['start']));

        $bounds = $envelope !== [] ? $envelope : $stages;
        $bedtime = $bounds !== [] ? min(array_column($bounds, 'start')) : null;
        $wakeTime = $bounds !== [] ? max(array_column($bounds, 'end')) : null;

        $window = ($bedtime !== null && $wakeTime !== null) ? $this->seconds($bedtime, $wakeTime) : 0;
        $duration = max(0, $window - $stageSeconds['awake']);

        return [
            'occurred_at' => $night,
            'bedtime' => $bedtime,
            'wake_time' => $wakeTime,
            'duration' => $duration,
            'awake' => $stageSeconds['awake'],
            'rem' => $stageSeconds['rem'],
            'core' => $stageSeconds['core'],
            'deep' => $stageSeconds['deep'],
            'source' => $source,
            'stages' => $stages,
        ];
    }

    /**
     * Split a night's segments into distinct sleep sessions on no-data gaps and
     * return the one with the most asleep time. Prevents a daytime nap or a
     * stray afternoon reading from being merged into the night sleep.
     *
     * @param  array<int, array{value: string, start: string, end: string}>  $segments
     * @return array<int, array{value: string, start: string, end: string}>
     */
    private function primarySession(array $segments): array
    {
        usort($segments, fn (array $first, array $second): int => strcmp($first['start'], $second['start']));

        $sessions = [];
        $current = [];
        $maxEnd = null;
        $lastAsleepEnd = null;

        foreach ($segments as $segment) {
            $asleep = ($column = self::STAGE_COLUMN[$segment['value']] ?? null) !== null && $column !== 'awake';

            $noDataGap = $maxEnd !== null && $segment['start'] > $maxEnd && $this->seconds($maxEnd, $segment['start']) > self::SESSION_GAP;
            $awakeGap = $asleep && $lastAsleepEnd !== null && $segment['start'] > $lastAsleepEnd && $this->seconds($lastAsleepEnd, $segment['start']) > self::SESSION_GAP;

            if ($noDataGap || $awakeGap) {
                $sessions[] = $current;
                $current = [];
            }

            $current[] = $segment;
            $maxEnd = ($maxEnd === null || $segment['end'] > $maxEnd) ? $segment['end'] : $maxEnd;

            if ($asleep && ($lastAsleepEnd === null || $segment['end'] > $lastAsleepEnd)) {
                $lastAsleepEnd = $segment['end'];
            }
        }

        if ($current !== []) {
            $sessions[] = $current;
        }

        usort($sessions, fn (array $first, array $second): int => $this->asleepSeconds($second) <=> $this->asleepSeconds($first));

        return $sessions[0] ?? [];
    }

    /**
     * Total asleep time (core + deep + REM) within a set of segments.
     *
     * @param  array<int, array{value: string, start: string, end: string}>  $segments
     */
    private function asleepSeconds(array $segments): int
    {
        $total = 0;

        foreach ($segments as $segment) {
            $column = self::STAGE_COLUMN[$segment['value']] ?? null;

            if ($column !== null && $column !== 'awake') {
                $total += $this->seconds($segment['start'], $segment['end']);
            }
        }

        return $total;
    }

    /**
     * The Apple sleep-day a stored bedtime belongs to (public entry point for
     * re-dating existing rows).
     */
    public function dayFor(string $bedtime): string
    {
        return $this->nightFor($bedtime);
    }

    /**
     * The day a sleep belongs to, matching Apple Health's 6pm-to-6pm sleep day:
     * a sleep is dated to the morning you wake, so anything starting at/after
     * 6pm rolls to the next calendar day and anything before stays put.
     */
    private function nightFor(string $start): string
    {
        $time = Carbon::parse($this->local($start));

        if ($time->hour >= 18) {
            $time = $time->addDay();
        }

        return $time->toDateString();
    }

    private function local(string $timestamp): string
    {
        return substr($timestamp, 0, 19);
    }

    private function seconds(string $start, string $end): int
    {
        return (int) abs(Carbon::parse($end)->diffInSeconds(Carbon::parse($start)));
    }

    private function normaliseSource(string $source): string
    {
        $value = strtolower($source);

        return match (true) {
            str_contains($value, 'oura') => Source::Oura->value,
            str_contains($value, 'watch') => Source::AppleWatch->value,
            str_contains($value, 'iphone') => Source::Iphone->value,
            default => Str::slug($value, '_') ?: 'unknown',
        };
    }

    /**
     * @param  list<string>  $available
     */
    private function preferredSource(array $available): ?string
    {
        foreach (self::SOURCE_PRIORITY as $source) {
            if (in_array($source, $available, true)) {
                return $source;
            }
        }

        sort($available);

        return $available[0] ?? null;
    }
}
