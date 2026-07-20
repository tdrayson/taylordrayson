<?php

namespace App\Support\Health;

class HealthPayloadSummary
{
    /**
     * Compact structural summary of a Health Auto Export payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function for(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $metrics = array_map(fn (array $metric): array => [
            'name' => $metric['name'] ?? null,
            'units' => $metric['units'] ?? null,
            'points' => is_array($metric['data'] ?? null) ? count($metric['data']) : 0,
            'fields' => is_array($metric['data'][0] ?? null) ? array_keys($metric['data'][0]) : [],
            'sample' => $metric['data'][0] ?? null,
        ], array_values(array_filter($data['metrics'] ?? [], 'is_array')));

        $workouts = array_map(fn (array $workout): array => [
            'name' => $workout['name'] ?? ($workout['workoutActivityType'] ?? null),
            'start' => $workout['start'] ?? null,
            'end' => $workout['end'] ?? null,
            'fields' => array_keys($workout),
        ], array_values(array_filter($data['workouts'] ?? [], 'is_array')));

        return [
            'top_level_keys' => array_keys($payload),
            'data_keys' => is_array($data) ? array_keys($data) : [],
            'metric_count' => count($metrics),
            'metrics' => $metrics,
            'workout_count' => count($workouts),
            'workouts' => $workouts,
        ];
    }
}
