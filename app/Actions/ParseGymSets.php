<?php

namespace App\Actions;

class ParseGymSets
{
    /**
     * @return array<int, array{exercise: string, reps: int, weight: float}>
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
     * @return array<int, array{exercise: string, reps: int, weight: float}>
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
                'weight' => $set['weight'],
            ]);
        }

        if (preg_match('/^(\d+)\s+rep(?:s)?:\s*([\d.,\s]+)\s*kg$/i', $details, $weightListMatches)) {
            $reps = (int) $weightListMatches[1];
            $weights = $this->parseWeightList($weightListMatches[2]);

            return array_map(
                fn (float $weight): array => [
                    'exercise' => $exercise,
                    'reps' => $reps,
                    'weight' => $weight,
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
                'weight' => $set['weight'],
            ];
        }

        return $sets;
    }

    /**
     * @return array{reps: int, weight: float}|null
     */
    private function parseSetSpecification(string $specification): ?array
    {
        if (! preg_match('/^(\d+)\s+rep(?:s)?\s+([\d.]+)\s*kg$/i', trim($specification), $matches)) {
            return null;
        }

        return [
            'reps' => (int) $matches[1],
            'weight' => (float) $matches[2],
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
