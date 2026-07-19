<?php

use App\Support\Health\HealthPayloadSummary;

it('summarises metrics and workouts compactly', function () {
    $summary = HealthPayloadSummary::for(['data' => [
        'metrics' => [['name' => 'sleep_analysis', 'units' => 'hr', 'data' => [['value' => 'Core']]]],
        'workouts' => [['name' => 'Run', 'start' => 'a', 'end' => 'b']],
    ]]);

    expect($summary['metric_count'])->toBe(1);
    expect($summary['metrics'][0]['name'])->toBe('sleep_analysis');
    expect($summary['metrics'][0]['points'])->toBe(1);
    expect($summary['workout_count'])->toBe(1);
});
