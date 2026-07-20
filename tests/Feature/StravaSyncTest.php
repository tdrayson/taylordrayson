<?php

use App\Console\Commands\Sync\StravaSync;
use App\Models\Activity;

it('maps an activity to a CSV row in header order', function () {
    $activity = Activity::factory()->make([
        'occurred_at' => '2026-06-28 07:30:00',
        'type' => 'run',
        'name' => 'Morning miles',
        'source_id' => '999',
        'meta' => ['polyline' => 'abc', 'average_speed' => 3.1],
    ]);

    $row = app(StravaSync::class)->csvRow($activity, ['occurred_at', 'type', 'name', 'source_id', 'meta']);

    expect($row)->toBe([
        '2026-06-28 07:30:00',
        'run',
        'Morning miles',
        '999',
        '{"polyline":"abc","average_speed":3.1}',
    ]);
});

it('json-encodes array stream columns instead of stringifying them', function () {
    $activity = Activity::factory()->make([
        'occurred_at' => '2026-06-28 07:30:00',
        'heart_rate' => [120, 130, 140],
        'altitude' => [10.5, 11.0],
        'meta' => ['average_speed' => 3.1],
    ]);

    $row = app(StravaSync::class)->csvRow($activity, ['occurred_at', 'heart_rate', 'altitude', 'meta']);

    expect($row)->toBe([
        '2026-06-28 07:30:00',
        '[120,130,140]',
        '[10.5,11]',
        '{"average_speed":3.1}',
    ]);
});

it('appends synced activities to the csv in header order', function () {
    $path = sys_get_temp_dir().'/activities_'.uniqid().'.csv';
    file_put_contents($path, "occurred_at,type,name,source,source_id,meta\n");

    $activity = Activity::factory()->create([
        'occurred_at' => '2026-06-28 07:30:00',
        'type' => 'run',
        'name' => 'CSV append test',
        'source' => 'strava',
        'source_id' => 'test-csv-append-999',
        'meta' => ['polyline' => 'abc'],
    ]);

    $count = app(StravaSync::class)->appendActivitiesToCsv([$activity], $path);

    expect($count)->toBe(1);

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    expect(end($lines))
        ->toContain('test-csv-append-999')
        ->toContain('CSV append test')
        ->toContain('2026-06-28 07:30:00');

    unlink($path);
});
