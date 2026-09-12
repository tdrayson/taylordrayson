<?php

use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Activity;
use App\Models\SyndicatedResponse;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(fn () => config(['services.strava.refresh_token' => 'test-token']));

/**
 * One page of summaries, then the empty page that ends pagination.
 *
 * A plain MockResponse would answer every page with the same summaries
 * forever: Saloon's URL-pattern fakes are reusable, not consumed, and don't
 * vary by query string. A counting closure is what actually terminates it.
 */
function fakeSummaries(array $summaries, array $extra = []): void
{
    $calls = 0;

    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/api/v3/athlete/activities*' => function () use (&$calls, $summaries): MockResponse {
            $calls++;

            return MockResponse::make($calls === 1 ? $summaries : []);
        },
        ...$extra,
    ]);
}

it('spends a detail request only where the count disagrees with what we hold', function () {
    $unchanged = Activity::factory()->create([
        'source' => Source::Strava->value, 'source_id' => '100', 'occurred_at' => now()->subDay(),
    ]);
    SyndicatedResponse::factory()->for($unchanged, 'target')->create([
        'source' => Source::Strava->value, 'kind' => WebmentionKind::Like,
    ]);

    Activity::factory()->create([
        'source' => Source::Strava->value, 'source_id' => '200', 'occurred_at' => now()->subDay(),
    ]);

    fakeSummaries([
        ['id' => 100, 'kudos_count' => 1, 'comment_count' => 0],
        ['id' => 200, 'kudos_count' => 1, 'comment_count' => 0],
    ], [
        '/api/v3/activities/200/kudos' => MockResponse::make([['firstname' => 'Justin', 'lastname' => 'M.']]),
        '/api/v3/activities/200/comments' => MockResponse::make([]),
    ]);

    $this->artisan('strava:responses')->assertSuccessful();

    // The one already holding its kudo was never asked about again.
    Saloon::assertNotSent(fn ($request): bool => str_contains($request->resolveEndpoint(), '/activities/100/'));
    expect(SyndicatedResponse::query()->count())->toBe(2);
});

// Without a cursor a second backfill would re-walk the newest activities and
// never reach the older half.
it('resumes the backfill where the request ceiling stopped it', function () {
    Cache::put('strava:responses:cursor', '100', now()->addWeek());

    Activity::factory()->create([
        'source' => Source::Strava->value, 'source_id' => '100', 'occurred_at' => now()->subDay(),
    ]);
    Activity::factory()->create([
        'source' => Source::Strava->value, 'source_id' => '200', 'occurred_at' => now()->subDays(2),
    ]);

    fakeSummaries([
        ['id' => 100, 'kudos_count' => 5, 'comment_count' => 0],
        ['id' => 200, 'kudos_count' => 1, 'comment_count' => 0],
    ], [
        '/api/v3/activities/200/kudos' => MockResponse::make([['firstname' => 'Justin', 'lastname' => 'M.']]),
        '/api/v3/activities/200/comments' => MockResponse::make([]),
    ]);

    $this->artisan('strava:responses --all')->assertSuccessful();

    Saloon::assertNotSent(fn ($request): bool => str_contains($request->resolveEndpoint(), '/activities/100/'));
    expect(SyndicatedResponse::query()->count())->toBe(1);
    expect(Cache::get('strava:responses:cursor'))->toBeNull();
});

it('asks about every activity when --all is passed, however the counts look', function () {
    $activity = Activity::factory()->create([
        'source' => Source::Strava->value, 'source_id' => '100', 'occurred_at' => now()->subYears(3),
    ]);
    SyndicatedResponse::factory()->for($activity, 'target')->create([
        'source' => Source::Strava->value, 'kind' => WebmentionKind::Like, 'author_name' => 'Gone A.',
    ]);

    fakeSummaries([['id' => 100, 'kudos_count' => 1, 'comment_count' => 0]], [
        '/api/v3/activities/100/kudos' => MockResponse::make([['firstname' => 'Justin', 'lastname' => 'M.']]),
        '/api/v3/activities/100/comments' => MockResponse::make([]),
    ]);

    $this->artisan('strava:responses --all')->assertSuccessful();

    // The stale name is what --all exists to repair.
    expect(SyndicatedResponse::query()->sole()->author_name)->toBe('Justin M.');
});

// A previous run's cursor can point at an activity that has since vanished
// from the stream (deleted on Strava, or the window simply moved past it).
// Left in place, it would strand every future --all doing nothing forever.
it('drops a cursor that never turns up in the stream, and says so', function () {
    Cache::put('strava:responses:cursor', '999', now()->addWeek());

    Activity::factory()->create([
        'source' => Source::Strava->value, 'source_id' => '100', 'occurred_at' => now()->subDay(),
    ]);

    fakeSummaries([['id' => 100, 'kudos_count' => 1, 'comment_count' => 0]], [
        '/api/v3/activities/100/kudos' => MockResponse::make([['firstname' => 'Justin', 'lastname' => 'M.']]),
        '/api/v3/activities/100/comments' => MockResponse::make([]),
    ]);

    $this->artisan('strava:responses --all')
        ->expectsOutputToContain('found nothing to resume from')
        ->assertSuccessful();

    // Nothing after the missing cursor was reachable this run either.
    Saloon::assertNotSent(fn ($request): bool => str_contains($request->resolveEndpoint(), '/activities/100/'));
    expect(SyndicatedResponse::query()->count())->toBe(0);
    expect(Cache::get('strava:responses:cursor'))->toBeNull();
});

// Strava's rate limit covers every request the command makes, not only the
// detail pulls: a backfill idling through thousands of already-synced
// activities must still stop paging once the budget is spent.
it('counts summary page fetches against the same budget as pulls', function () {
    $calls = 0;

    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/api/v3/athlete/activities*' => function () use (&$calls): MockResponse {
            $calls++;

            // An id nothing in the database will ever match: no pull is ever
            // spent, so the only cost of paginating this far is the page itself.
            return MockResponse::make([['id' => 900_000 + $calls, 'kudos_count' => 0, 'comment_count' => 0]]);
        },
    ]);

    $this->artisan('strava:responses --all')->assertSuccessful();

    expect($calls)->toBe(150);
});
