<?php

namespace App\Actions\Workouts;

use App\Actions\ParseGymSets;
use App\Data\SetgraphWorkout;

/**
 * Turn the plain text Setgraph puts on the share sheet into a {@see SetgraphWorkout}.
 *
 * The share is a list of exercise lines, then a summary line naming the workout
 * type and its length, then a trailing credit:
 *
 *     Lat Pulldown • 12 rep: 32, 36, 36 kg
 *     Trx push up • 3 sets: 12 rep
 *
 *     Other • 38 min
 *
 *     Tracked on Setgraph
 *
 * Exercise lines go to {@see ParseGymSets}. The summary line is picked out by
 * yielding no sets rather than by position.
 */
class ParseSetgraphWorkout
{
    /** Both parts are optional: Setgraph writes past the hour as "1 h 4 min". */
    private const DURATION_PATTERN = '/^(?<label>.+?)\s*[•·]\s*(?=\d)(?:(?<hours>\d+)\s*(?:hours|hour|hrs|hr|h)\b)?\s*(?:(?<minutes>\d+)\s*(?:minutes|minute|mins|min)\b)?$/iu';

    public function __construct(private ParseGymSets $parseSets) {}

    public function __invoke(string $text): SetgraphWorkout
    {
        [$duration, $label] = $this->summary($text);

        return new SetgraphWorkout(
            sets: ($this->parseSets)($text),
            duration: $duration,
            label: $label,
        );
    }

    /**
     * The workout's stated length in seconds and the label in front of it, or
     * nulls when no line carries one.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function summary(string $text): array
    {
        $summary = [null, null];

        foreach (preg_split('/\R/', trim($text)) as $line) {
            $line = trim($line);

            // A line that yields sets is an exercise, not the summary.
            if (($this->parseSets)($line) !== []) {
                continue;
            }

            if (! preg_match(self::DURATION_PATTERN, $line, $matches)) {
                continue;
            }

            $hours = (int) ($matches['hours'] ?? 0);
            $minutes = (int) ($matches['minutes'] ?? 0);

            if ($hours === 0 && $minutes === 0) {
                continue;
            }

            // Last match wins: a timed exercise ("Plank • 2 min") matches too.
            $summary = [$hours * 3600 + $minutes * 60, trim($matches['label'])];
        }

        return $summary;
    }
}
