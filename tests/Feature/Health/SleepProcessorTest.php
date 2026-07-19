<?php

use App\Models\Sleep;
use App\Support\Health\SleepProcessor;

it('upserts a night of sleep from a decoded payload', function () {
    $payload = [
        'data' => ['metrics' => [[
            'name' => 'sleep_analysis',
            'data' => [
                ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 02:30:00 +0000'],
                ['value' => 'Deep', 'source' => 'Oura', 'start' => '2026-01-11 02:30:00 +0000', 'end' => '2026-01-11 04:00:00 +0000'],
                ['value' => 'REM',  'source' => 'Oura', 'start' => '2026-01-11 04:00:00 +0000', 'end' => '2026-01-11 07:00:00 +0000'],
            ],
        ]]],
    ];

    // A non-existent CSV path: mirrorToCsv() is a no-op when the target file
    // doesn't exist yet, so this keeps the test from touching the real
    // data/sleep.csv while still exercising process() end to end.
    $csvPath = sys_get_temp_dir().'/sleep_processor_test_'.uniqid().'.csv';

    app(SleepProcessor::class)->process($payload, $csvPath);

    expect(Sleep::query()->count())->toBe(1);
    $night = Sleep::query()->first();
    expect($night->source)->toBe('oura');
    expect($night->duration)->toBeGreaterThan(0);
});

it('is idempotent across repeated payloads', function () {
    $payload = [
        'data' => ['metrics' => [[
            'name' => 'sleep_analysis',
            'data' => [
                ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 03:00:00 +0000'],
                ['value' => 'REM',  'source' => 'Oura', 'start' => '2026-01-11 03:00:00 +0000', 'end' => '2026-01-11 07:00:00 +0000'],
            ],
        ]]],
    ];

    $csvPath = sys_get_temp_dir().'/sleep_processor_test_'.uniqid().'.csv';

    app(SleepProcessor::class)->process($payload, $csvPath);
    app(SleepProcessor::class)->process($payload, $csvPath);

    expect(Sleep::query()->count())->toBe(1);
});
