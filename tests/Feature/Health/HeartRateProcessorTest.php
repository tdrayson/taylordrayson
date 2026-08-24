<?php

use App\Models\Activity;
use App\Support\Health\HeartRateProcessor;

it('fills missing heart-rate on an activity from window-matched samples', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-01-10 09:00:00',
        'duration' => 1800,
        'altitude' => null,
        'average_heart_rate' => null,
        'max_heart_rate' => null,
        'heart_rate' => null,
    ]);

    $payload = ['data' => ['metrics' => [[
        'name' => 'heart_rate',
        'data' => [
            ['start' => '2026-01-10 09:05:00 +0000', 'Avg' => 130, 'Max' => 150, 'source' => 'Apple Watch'],
            ['start' => '2026-01-10 09:15:00 +0000', 'Avg' => 140, 'Max' => 165, 'source' => 'Apple Watch'],
        ],
    ]]]];

    app(HeartRateProcessor::class)->process($payload);

    $activity->refresh();
    expect($activity->average_heart_rate)->not->toBeNull();
    expect($activity->max_heart_rate)->toBeGreaterThanOrEqual(150);
});

it('does not clobber an activity that already has a Strava stream', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-01-10 09:00:00',
        'duration' => 1800,
        'altitude' => [1, 2, 3],
        'average_heart_rate' => 120,
        'max_heart_rate' => 145,
    ]);

    $payload = ['data' => ['metrics' => [[
        'name' => 'heart_rate',
        'data' => [['start' => '2026-01-10 09:05:00 +0000', 'Avg' => 200, 'Max' => 210, 'source' => 'Apple Watch']],
    ]]]];

    app(HeartRateProcessor::class)->process($payload);

    $activity->refresh();
    expect($activity->average_heart_rate)->toBe(120);
    expect($activity->max_heart_rate)->toBe(145);
});
