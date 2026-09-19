<?php

use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Activity;
use App\Models\SyndicatedResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(fn () => config(['services.strava.refresh_token' => 'test-token']));
afterEach(fn () => Carbon::setTestNow());

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
// never reach the older half. The cursor is the activity the last run stopped
// at without pulling, so the resume has to start there and not after it.
it('resumes the backfill at the activity the request ceiling stopped it on', function () {
    Cache::put('strava:responses:cursor', '200', now()->addWeek());

    foreach ([100, 200, 300] as $offset => $id) {
        Activity::factory()->create([
            'source' => Source::Strava->value,
            'source_id' => (string) $id,
            'occurred_at' => now()->subDays($offset + 1),
        ]);
    }

    fakeSummaries([
        ['id' => 100, 'kudos_count' => 5, 'comment_count' => 0],
        ['id' => 200, 'kudos_count' => 1, 'comment_count' => 0],
        ['id' => 300, 'kudos_count' => 1, 'comment_count' => 0],
    ], [
        '/api/v3/activities/*/kudos' => MockResponse::make([['firstname' => 'Justin', 'lastname' => 'M.']]),
        '/api/v3/activities/*/comments' => MockResponse::make([]),
    ]);

    $this->artisan('strava:responses --all')->assertSuccessful();

    // Above the cursor: done last run, not paid for again.
    Saloon::assertNotSent(fn ($request): bool => str_contains($request->resolveEndpoint(), '/activities/100/'));

    // The cursor itself and everything below it: this run's work. Skipping the
    // cursor would silently drop one activity per interruption.
    expect(Activity::query()->whereHas('syndicatedResponses')->pluck('source_id')->sort()->values()->all())
        ->toBe(['200', '300']);
    expect(Cache::get('strava:responses:cursor'))->toBeNull();
});

it('still fetches under --all when the counts agree but are not zero', function () {
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

// A missed run should not strand an activity's responses past --days: the
// window widens to the newest response already held, the way strava:sync
// widens to the newest stored activity.
it('widens the window to the newest stored response when a gap is longer than --days', function () {
    Carbon::setTestNow('2026-01-15 00:00:00');

    $activity = Activity::factory()->create([
        'source' => Source::Strava->value, 'source_id' => '100', 'occurred_at' => now()->subDays(15),
    ]);
    SyndicatedResponse::factory()->for($activity, 'target')->create([
        'source' => Source::Strava->value, 'kind' => WebmentionKind::Like, 'occurred_at' => now()->subDays(15),
    ]);

    fakeSummaries([]);

    $this->artisan('strava:responses')->assertSuccessful();

    $expected = now()->subDays(15)->timestamp;

    Saloon::assertSent(fn ($request): bool => str_contains($request->resolveEndpoint(), '/athlete/activities')
        && (int) $request->query()->get('after') === $expected);
});

it('caps the self-heal at 90 days when the gap is much longer than that', function () {
    Carbon::setTestNow('2026-01-15 00:00:00');

    $activity = Activity::factory()->create([
        'source' => Source::Strava->value, 'source_id' => '100', 'occurred_at' => now()->subDays(200),
    ]);
    SyndicatedResponse::factory()->for($activity, 'target')->create([
        'source' => Source::Strava->value, 'kind' => WebmentionKind::Like, 'occurred_at' => now()->subDays(200),
    ]);

    fakeSummaries([]);

    $this->artisan('strava:responses')
        ->expectsOutputToContain('catching up only that far')
        ->assertSuccessful();

    $expected = now()->subDays(90)->timestamp;

    Saloon::assertSent(fn ($request): bool => str_contains($request->resolveEndpoint(), '/athlete/activities')
        && (int) $request->query()->get('after') === $expected);
});

// Production holds ~1,500 activities and most have nothing on them. Asking
// Strava about each costs two requests against a 150 ceiling, which is hours
// of waiting to be told twice over that there is nothing there.
it('skips an activity under --all when both sides are empty', function () {
    Activity::factory()->create([
        'source' => Source::Strava->value, 'source_id' => '100', 'occurred_at' => now()->subYears(3),
    ]);

    fakeSummaries([['id' => 100, 'kudos_count' => 0, 'comment_count' => 0]]);

    $this->artisan('strava:responses --all')->assertSuccessful();

    Saloon::assertNotSent(fn ($request): bool => str_contains($request->resolveEndpoint(), '/activities/100/'));
});

// A 0/0 summary against rows we still hold is a withdrawal, not a no-op: the
// fetch is what lets the reconcile clear them.
it('still fetches under --all when the summary is empty but rows are held', function () {
    $activity = Activity::factory()->create([
        'source' => Source::Strava->value, 'source_id' => '100', 'occurred_at' => now()->subYears(3),
    ]);
    SyndicatedResponse::factory()->for($activity, 'target')->create([
        'source' => Source::Strava->value, 'kind' => WebmentionKind::Like, 'author_name' => 'Took it back A.',
    ]);

    fakeSummaries([['id' => 100, 'kudos_count' => 0, 'comment_count' => 0]], [
        '/api/v3/activities/100/kudos' => MockResponse::make([]),
        '/api/v3/activities/100/comments' => MockResponse::make([]),
    ]);

    $this->artisan('strava:responses --all')->assertSuccessful();

    expect(SyndicatedResponse::query()->count())->toBe(0);
});

// The property the whole backfill rests on: interrupted by the request
// ceiling and run again, every activity is covered exactly once. An
// off-by-one in the cursor silently drops one activity per interruption,
// which no single-run assertion would catch.
it('covers every activity across a backfill the ceiling interrupts', function () {
    for ($i = 1; $i <= 80; $i++) {
        Activity::factory()->create([
            'source' => Source::Strava->value,
            'source_id' => (string) (1000 + $i),
            'occurred_at' => now()->subDays($i),
        ]);
    }

    $summaries = collect(range(1, 80))
        ->map(fn (int $i): array => ['id' => 1000 + $i, 'kudos_count' => 1, 'comment_count' => 0])
        ->all();

    $details = [
        '/api/v3/activities/*/kudos' => MockResponse::make([['firstname' => 'Justin', 'lastname' => 'M.']]),
        '/api/v3/activities/*/comments' => MockResponse::make([]),
    ];

    fakeSummaries($summaries, $details);
    $this->artisan('strava:responses --all')
        ->expectsOutputToContain('Run again to continue')
        ->assertSuccessful();

    // The ceiling really did stop it partway, or the rest proves nothing.
    expect(Activity::query()->whereHas('syndicatedResponses')->count())->toBeLessThan(80);

    fakeSummaries($summaries, $details);
    $this->artisan('strava:responses --all')->assertSuccessful();

    expect(Activity::query()->whereHas('syndicatedResponses')->count())->toBe(80)
        ->and(SyndicatedResponse::query()->count())->toBe(80)
        ->and(Cache::get('strava:responses:cursor'))->toBeNull();
});
