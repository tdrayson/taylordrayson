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
    private const DURATION_PATTERN = '/^(?<label>.+?)\s*[•·]\s*(?<value>\d+)\s*(?<unit>min|mins|minutes|h|hr|hrs|hour|hours)$/iu';

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
        foreach (preg_split('/\R/', trim($text)) as $line) {
            if (! preg_match(self::DURATION_PATTERN, trim($line), $matches)) {
                continue;
            }

            $multiplier = str_starts_with(strtolower($matches['unit']), 'h') ? 3600 : 60;

            return [(int) $matches['value'] * $multiplier, trim($matches['label'])];
        }

        return [null, null];
    }
}
