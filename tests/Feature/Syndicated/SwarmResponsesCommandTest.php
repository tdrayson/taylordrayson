<?php

use App\Enums\Source;
use App\Models\Checkin;
use App\Models\SyndicatedResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config(['services.foursquare.access_token' => 'test-token']);
    Http::fake(['fastly.4sqi.net/*' => Http::response('', 404)]);
});
afterEach(fn () => Carbon::setTestNow());

/** One page of check-ins, then the empty page that ends pagination. */
function fakeSwarmPage(array $items): void
{
    Saloon::fake([
        MockResponse::make(['response' => ['checkins' => ['items' => $items]]]),
        MockResponse::make(['response' => ['checkins' => ['items' => []]]]),
    ]);
}

it('stores the likes reported for a check-in it already holds', function () {
    $checkin = Checkin::factory()->create([
        'source' => Source::Swarm->value, 'source_id' => 'abc', 'occurred_at' => now()->subDay(),
    ]);

    fakeSwarmPage([[
        'id' => 'abc',
        'likes' => ['count' => 1, 'groups' => [['items' => [['id' => '1', 'displayName' => 'Luke Allen']]]]],
        'comments' => ['count' => 0],
    ]]);

    $this->artisan('swarm:responses')->assertSuccessful();

    expect($checkin->syndicatedResponses()->sole()->author_name)->toBe('Luke Allen');
});

it('ignores a check-in that is not stored here', function () {
    fakeSwarmPage([[
        'id' => 'not-ours',
        'likes' => ['count' => 1, 'groups' => [['items' => [['id' => '1', 'displayName' => 'Luke Allen']]]]],
    ]]);

    $this->artisan('swarm:responses')->assertSuccessful();

    expect(SyndicatedResponse::query()->count())->toBe(0);
});

it('fails gracefully when credentials are missing', function () {
    config(['services.foursquare.access_token' => null]);

    $this->artisan('swarm:responses')->assertFailed();

    expect(SyndicatedResponse::query()->count())->toBe(0);
});

// A missed run should not strand a check-in's responses past --days: the
// window widens to the newest response already held.
it('widens the window to the newest stored response when a gap is longer than --days', function () {
    Carbon::setTestNow('2026-01-15 00:00:00');

    $checkin = Checkin::factory()->create([
        'source' => Source::Swarm->value, 'source_id' => 'abc', 'occurred_at' => now()->subDays(15),
    ]);
    SyndicatedResponse::factory()->for($checkin, 'target')->create([
        'source' => Source::Swarm->value, 'occurred_at' => now()->subDays(15),
    ]);

    fakeSwarmPage([]);

    $this->artisan('swarm:responses')->assertSuccessful();

    $expected = now()->subDays(15)->timestamp;

    Saloon::assertSent(fn ($request): bool => (int) $request->query()->get('afterTimestamp') === $expected);
});

it('caps the self-heal at 90 days when the gap is much longer than that', function () {
    Carbon::setTestNow('2026-01-15 00:00:00');

    $checkin = Checkin::factory()->create([
        'source' => Source::Swarm->value, 'source_id' => 'abc', 'occurred_at' => now()->subDays(200),
    ]);
    SyndicatedResponse::factory()->for($checkin, 'target')->create([
        'source' => Source::Swarm->value, 'occurred_at' => now()->subDays(200),
    ]);

    fakeSwarmPage([]);

    $this->artisan('swarm:responses')
        ->expectsOutputToContain('catching up only that far')
        ->assertSuccessful();

    $expected = now()->subDays(90)->timestamp;

    Saloon::assertSent(fn ($request): bool => (int) $request->query()->get('afterTimestamp') === $expected);
});
