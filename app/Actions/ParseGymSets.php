<?php

namespace App\Actions;

/**
 * Parse a Setgraph-style gym log into flat set entries, one per set performed.
 *
 * Weight is emitted as `weight_kg` to match the shape already stored in
 * `activities.meta.sets`. A set with no stated weight is bodyweight and carries
 * `0.0`, which the entry view renders as "Bodyweight".
 */
class ParseGymSets
{
    /**
     * @return array<int, array{exercise: string, reps: int, weight_kg: float}>
     */
    public function __invoke(string $text): array
    {
        $sets = [];

        foreach (preg_split('/\R/', trim($text)) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            array_push($sets, ...$this->parseLine($line));
        }

        return $sets;
    }

    /**
     * @return array<int, array{exercise: string, reps: int, weight_kg: float}>
     */
    private function parseLine(string $line): array
    {
        if (! preg_match('/^(.+?)\s*[•·]\s*(.+)$/u', $line, $matches)) {
            return [];
        }

        $exercise = trim($matches[1]);
        $details = trim($matches[2]);

        if (preg_match('/^(\d+)\s+sets?:\s*(.+)$/i', $details, $setCountMatches)) {
            $setCount = (int) $setCountMatches[1];
            $template = trim($setCountMatches[2]);
            $set = $this->parseSetSpecification($template);

            if ($set === null) {
                return [];
            }

            return array_fill(0, $setCount, [
                'exercise' => $exercise,
                'reps' => $set['reps'],
                'weight_kg' => $set['weight_kg'],
            ]);
        }

        if (preg_match('/^(\d+)\s+rep(?:s)?:\s*([\d.,\s]+)\s*kg$/i', $details, $weightListMatches)) {
            $reps = (int) $weightListMatches[1];
            $weights = $this->parseWeightList($weightListMatches[2]);

            return array_map(
                fn (float $weight): array => [
                    'exercise' => $exercise,
                    'reps' => $reps,
                    'weight_kg' => $weight,
                ],
                $weights,
            );
        }

        $sets = [];

        foreach (array_map(trim(...), explode(',', $details)) as $specification) {
            $set = $this->parseSetSpecification($specification);

            if ($set === null) {
                continue;
            }

            $sets[] = [
                'exercise' => $exercise,
                'reps' => $set['reps'],
                'weight_kg' => $set['weight_kg'],
            ];
        }

        return $sets;
    }

    /**
     * A weight is optional: "12 rep" is a bodyweight set (TRX, press-ups) and
     * scores 0 kg, which is how the entry view spots it.
     *
     * @return array{reps: int, weight_kg: float}|null
     */
    private function parseSetSpecification(string $specification): ?array
    {
        if (! preg_match('/^(\d+)\s+rep(?:s)?(?:\s+([\d.]+)\s*kg)?$/i', trim($specification), $matches)) {
            return null;
        }

        return [
            'reps' => (int) $matches[1],
            'weight_kg' => (float) ($matches[2] ?? 0),
        ];
    }

    /**
     * @return array<int, float>
     */
    private function parseWeightList(string $weights): array
    {
        return array_values(array_filter(array_map(
            fn (string $weight): ?float => $weight === '' ? null : (float) $weight,
            array_map(trim(...), explode(',', $weights)),
        )));
    }
}
