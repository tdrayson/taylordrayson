<?php

use App\Models\Activity;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

function fakeStravaSync(): void
{
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/athlete/activities*' => MockResponse::make([]),
    ]);
}

/** The `after` timestamp the command asked Strava for. */
function stravaAfter(): int
{
    $sent = 0;

    Saloon::assertSent(function ($request, $response) use (&$sent): bool {
        if (str_contains($response->getPendingRequest()->getUrl(), 'athlete/activities')) {
            $sent = (int) $request->query()->get('after');
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

    Saloon::assertSent(function ($request, $response) use (&$count): bool {
        if (preg_match('#/api/v3/activities/\d+$#', parse_url($response->getPendingRequest()->getUrl(), PHP_URL_PATH) ?? '')) {
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

    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/athlete/activities*' => mockSequence([

            MockResponse::make([stravaSummary(['name' => 'Parkrun PB'])]),

            MockResponse::make([]),

        ]),
        '/api/v3/activities/555' => MockResponse::make(stravaSummary([
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

    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/athlete/activities*' => mockSequence([

            MockResponse::make([stravaSummary()]),

            MockResponse::make([]),

        ]),
        '/api/v3/activities/*' => MockResponse::make([]),
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

    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/athlete/activities*' => mockSequence([

            MockResponse::make([stravaSummary()]),

            MockResponse::make([]),

        ]),
        '/api/v3/activities/555' => MockResponse::make(stravaSummary([
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

    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/athlete/activities*' => mockSequence([

            MockResponse::make([stravaSummary([
                'start_date' => now()->subDays(30)->format('Y-m-d\TH:i:s\Z'),
                'start_date_local' => now()->subDays(30)->format('Y-m-d\TH:i:s\Z'),
            ])]),

            MockResponse::make([]),

        ]),
        '/api/v3/activities/*' => MockResponse::make([]),
    ]);

    $this->artisan('strava:sync --days=2 --refresh')->assertSuccessful();

    expect(stravaDetailRequests())->toBe(0);
});

/*
 * Strava names the first IANA zone matching the device's UTC offset when an
 * activity has no GPS, so an indoor session in London arrives as Africa/Algiers
 * in summer and Africa/Abidjan in winter (#285).
 */
it('does not store a zone Strava guessed from the offset', function () {
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/athlete/activities*' => mockSequence([

            MockResponse::make([stravaSummary(['id' => 777, 'sport_type' => 'WeightTraining', 'timezone' => '(GMT+01:00) Africa/Algiers'])]),

            MockResponse::make([]),

        ]),
        '/api/v3/activities/777/streams*' => MockResponse::make([]),
        '/api/v3/activities/777' => MockResponse::make(stravaSummary([
            'id' => 777,
            'sport_type' => 'WeightTraining',
            'timezone' => '(GMT+01:00) Africa/Algiers',
        ])),
    ]);

    $this->artisan('strava:sync --days=7')->assertSuccessful();

    expect(Activity::where('source_id', '777')->first()->timezone)->toBeNull();
});

it('stores a foreign zone when the activity has a route to back it up', function () {
    fakeMapImages();

    $abroad = [
        'id' => 778,
        'timezone' => '(GMT-05:00) America/New_York',
        'map' => ['polyline' => 'ki{eFvqfiVsAvJ'],
    ];

    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/athlete/activities*' => mockSequence([

            MockResponse::make([stravaSummary($abroad)]),

            MockResponse::make([]),

        ]),
        '/api/v3/activities/778/streams*' => MockResponse::make([]),
        '/api/v3/activities/778' => MockResponse::make(stravaSummary($abroad)),
    ]);

    $this->artisan('strava:sync --days=7')->assertSuccessful();

    expect(Activity::where('source_id', '778')->first()->timezone)->toBe('America/New_York');
});
