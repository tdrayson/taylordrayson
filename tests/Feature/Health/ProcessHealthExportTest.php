<?php

use App\Jobs\ProcessHealthExport;
use App\Models\Activity;
use App\Models\Sleep;
use App\Support\Health\HeartRateProcessor;
use App\Support\Health\SleepProcessor;

it('runs the processor the endpoint chose', function () {
    $payload = ['data' => ['metrics' => [[
        'name' => 'sleep_analysis',
        'data' => [
            ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 03:00:00 +0000'],
            ['value' => 'REM', 'source' => 'Oura', 'start' => '2026-01-11 03:00:00 +0000', 'end' => '2026-01-11 07:00:00 +0000'],
        ],
    ]]]];

    (new ProcessHealthExport($payload, SleepProcessor::class))->handle();

    expect(Sleep::query()->count())->toBe(1);
});

it('matches heart rate samples to the activity they fall inside', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-01-10 09:00:00',
        'duration' => 1800,
        'timezone' => null,
        'altitude' => null,
        'average_heart_rate' => null,
        'max_heart_rate' => null,
    ]);

    $payload = ['data' => ['metrics' => [
        ['name' => 'heart_rate', 'data' => [['start' => '2026-01-10 09:05:00 +0000', 'Avg' => 130, 'Max' => 150, 'source' => 'Apple Watch']]],
    ]]];

    (new ProcessHealthExport($payload, HeartRateProcessor::class))->handle();

    expect($activity->refresh()->average_heart_rate)->toBe(130)
        ->and($activity->max_heart_rate)->toBe(150);
});
