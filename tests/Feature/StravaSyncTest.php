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

/**
 * A Strava summary for an activity we already hold, plus whatever the test
 * wants to differ from the stored row.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stravaSummary(array $overrides = []): array
{
    return [
        'id' => 555,
        'name' => 'Morning Run',
        'sport_type' => 'Run',
        'moving_time' => 1800,
        'distance' => 5000.0,
        'total_photo_count' => 0,
        'start_date' => stravaRecent(),
        'start_date_local' => stravaRecent(),
        'timezone' => '(GMT+00:00) Europe/London',
        ...$overrides,
    ];
}

/** A start time inside the default refresh window. */
function stravaRecent(): string
{
    return now()->subHours(3)->format('Y-m-d\TH:i:s\Z');
}

/** How many times the command asked for an activity's detail. */
function stravaDetailRequests(): int
{
    $count = 0;

    Http::assertSent(function ($request) use (&$count): bool {
        if (preg_match('#/api/v3/activities/\d+$#', parse_url($request->url(), PHP_URL_PATH) ?? '')) {
            $count++;
        }

        return true;
    });

    return $count;
}

it('picks up a title and description edited after the activity was published', function () {
    Activity::factory()->create([
        'source' => 'strava',
        'source_id' => '555',
        'name' => 'Morning Run',
        'description' => null,
        'type' => 'run',
        'duration' => 1800,
        'distance' => 5000,
        'occurred_at' => now()->subHours(3),
    ]);

    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        '*/athlete/activities*' => Http::sequence()
            ->push([stravaSummary(['name' => 'Parkrun PB'])])
            ->push([]),
        '*/api/v3/activities/555' => Http::response(stravaSummary([
            'name' => 'Parkrun PB',
            'description' => 'Took two minutes off.',
        ])),
    ]);

    $this->artisan('strava:sync --days=7')->assertSuccessful();

    expect(Activity::where('source_id', '555')->first())
        ->name->toBe('Parkrun PB')
        ->description->toBe('Took two minutes off.');
});

it('spends no detail request on an activity whose summary still matches', function () {
    Activity::factory()->create([
        'source' => 'strava',
        'source_id' => '555',
        'name' => 'Morning Run',
        'type' => 'run',
        'duration' => 1800,
        'distance' => 5000,
        'occurred_at' => now()->subHours(3),
    ]);

    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        '*/athlete/activities*' => Http::sequence()->push([stravaSummary()])->push([]),
        '*/api/v3/activities/*' => Http::response([]),
    ]);

    $this->artisan('strava:sync --days=7')->assertSuccessful();

    // The guarantee that keeps a five-minute cron inside Strava's rate limit.
    expect(stravaDetailRequests())->toBe(0);
});

it('re-fetches an unchanged activity when --refresh is passed', function () {
    Activity::factory()->create([
        'source' => 'strava',
        'source_id' => '555',
        'name' => 'Morning Run',
        'description' => null,
        'type' => 'run',
        'duration' => 1800,
        'distance' => 5000,
        'occurred_at' => now()->subHours(3),
    ]);

    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        '*/athlete/activities*' => Http::sequence()->push([stravaSummary()])->push([]),
        '*/api/v3/activities/555' => Http::response(stravaSummary([
            'description' => 'Written the next morning.',
        ])),
    ]);

    $this->artisan('strava:sync --days=7 --refresh')->assertSuccessful();

    // A description edited on its own leaves the summary identical, so only
    // --refresh can see it.
    expect(Activity::where('source_id', '555')->first()->description)
        ->toBe('Written the next morning.');
});

it('holds the refresh to --days even when the window stretched to heal a gap', function () {
    // The newest activity is 30 days old, so resolveAfterTimestamp() stretches
    // the fetch back that far. The refresh must not follow it, or a cron outage
    // returns and re-fetches every detail in the gap at once.
    Activity::factory()->create([
        'source' => 'strava',
        'source_id' => '555',
        'name' => 'Morning Run',
        'type' => 'run',
        'duration' => 1800,
        'distance' => 5000,
        'occurred_at' => now()->subDays(30),
    ]);

    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        '*/athlete/activities*' => Http::sequence()->push([stravaSummary([
            'start_date' => now()->subDays(30)->format('Y-m-d\TH:i:s\Z'),
            'start_date_local' => now()->subDays(30)->format('Y-m-d\TH:i:s\Z'),
        ])])->push([]),
        '*/api/v3/activities/*' => Http::response([]),
    ]);

    $this->artisan('strava:sync --days=2 --refresh')->assertSuccessful();

    expect(stravaDetailRequests())->toBe(0);
});
