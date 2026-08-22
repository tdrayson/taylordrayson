<?php

use App\Console\Commands\Sync\StravaSync;
use App\Models\Activity;
use Illuminate\Support\Facades\Http;

function fakeStravaSync(): void
{
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        '*/athlete/activities*' => Http::response([]),
    ]);
}

/** The `after` timestamp the command asked Strava for. */
function stravaAfter(): int
{
    $sent = 0;

    Http::assertSent(function ($request) use (&$sent): bool {
        if (str_contains($request->url(), 'athlete/activities')) {
            $sent = (int) $request['after'];
        }

        return true;
    });

    return $sent;
}

it('asks for the --days window when activities are current', function () {
    fakeStravaSync();
    Activity::factory()->create(['source' => 'strava', 'source_id' => '1', 'occurred_at' => now()->subHours(6)]);

    $this->artisan('strava:sync --days=7')->assertSuccessful();

    expect(stravaAfter())->toBe(now()->subDays(7)->timestamp);
});

it('extends the window back to the newest stored activity when a gap has opened', function () {
    fakeStravaSync();
    $newest = now()->subDays(30);
    Activity::factory()->create(['source' => 'strava', 'source_id' => '1', 'occurred_at' => $newest]);

    $this->artisan('strava:sync --days=7')->assertSuccessful();

    // Without this a missed run strands every activity older than --days.
    expect(stravaAfter())->toBe($newest->copy()->startOfDay()->timestamp);
});

it('caps the catch-up so a long gap does not refetch all history', function () {
    fakeStravaSync();
    Activity::factory()->create(['source' => 'strava', 'source_id' => '1', 'occurred_at' => now()->subYears(3)]);

    $this->artisan('strava:sync --days=7')->assertSuccessful();

    expect(stravaAfter())->toBeGreaterThanOrEqual(now()->subDays(91)->timestamp);
});

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
