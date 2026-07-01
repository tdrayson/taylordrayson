<?php

use App\Console\Commands\Sync\StravaSync;
use App\Models\Activity;

it('maps an activity to a CSV row in header order', function () {
    $activity = Activity::factory()->make([
        'occurred_at' => '2026-06-28 07:30:00',
        'type' => 'run',
        'name' => 'Morning miles',
        'platform_id' => '999',
        'meta' => ['polyline' => 'abc', 'average_speed' => 3.1],
    ]);

    $row = app(StravaSync::class)->csvRow($activity, ['occurred_at', 'type', 'name', 'platform_id', 'meta']);

    expect($row)->toBe([
        '2026-06-28 07:30:00',
        'run',
        'Morning miles',
        '999',
        '{"polyline":"abc","average_speed":3.1}',
    ]);
});

it('appends synced activities to the csv in header order', function () {
    $path = sys_get_temp_dir().'/activities_'.uniqid().'.csv';
    file_put_contents($path, "occurred_at,type,name,platform_type,platform_id,meta\n");

    $activity = Activity::factory()->create([
        'occurred_at' => '2026-06-28 07:30:00',
        'type' => 'run',
        'name' => 'CSV append test',
        'platform_type' => 'strava',
        'platform_id' => 'test-csv-append-999',
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
