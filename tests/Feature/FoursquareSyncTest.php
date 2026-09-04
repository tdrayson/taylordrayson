<?php

use App\Jobs\GenerateEntryMap;
use App\Models\Checkin;
use Illuminate\Support\Facades\Queue;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config(['services.foursquare.access_token' => 'test-token']);
});

/**
 * Fake one page of check-ins per run, each followed by the empty page that
 * ends pagination. Pass one array per `foursquare:sync` invocation.
 *
 * Built as a single sequence rather than by calling `Saloon::fake()` per run:
 * a second `fake()` replaces the mock client, which would lose the responses
 * queued for the earlier runs.
 *
 * @param  list<list<array<string, mixed>>>  $runs
 */
function fakeSwarmRuns(array $runs): void
{
    $responses = [];

    foreach ($runs as $items) {
        $responses[] = MockResponse::make(['response' => ['checkins' => ['items' => $items]]]);
        $responses[] = MockResponse::make(['response' => ['checkins' => ['items' => []]]]);
    }

    // Unkeyed, so the responses are handed out in order across every run.
    Saloon::fake($responses);
}

function swarmItem(string $id, ?string $shout = null): array
{
    return array_filter([
        'id' => $id,
        'createdAt' => now()->subMinutes(5)->timestamp,
        'shout' => $shout,
        'venue' => [
            'name' => 'Coffee Bar',
            'categories' => [['name' => 'Café']],
            'location' => ['city' => 'London', 'lat' => 51.5, 'lng' => -0.1],
        ],
    ], fn (mixed $value): bool => $value !== null);
}

it('re-checks the --days window when check-ins are current', function () {
    Checkin::factory()->create([
        'source' => 'swarm',
        'source_id' => 'recent',
        'occurred_at' => now()->subHours(6),
    ]);

    fakeSwarmRuns([[]]);

    $this->artisan('foursquare:sync --days=2')->assertSuccessful();

    // Deliberate overlap: the window is re-checked so a shout or photo added
    // after the fact lands on the existing row.
    Saloon::assertSent(fn ($request, $response) => (int) $request->query()->get('afterTimestamp') === now()->subDays(2)->timestamp);
});

it('extends the window back to the newest stored check-in when a gap has opened', function () {
    $newest = now()->subDays(10);
    Checkin::factory()->create([
        'source' => 'swarm',
        'source_id' => 'stale',
        'occurred_at' => $newest,
    ]);

    fakeSwarmRuns([[]]);

    $this->artisan('foursquare:sync --days=2')->assertSuccessful();

    // Without this a missed run strands the gap behind the fixed window forever.
    Saloon::assertSent(fn ($request, $response) => (int) $request->query()->get('afterTimestamp') === $newest->timestamp);
});

it('caps the catch-up so a long gap does not refetch all history', function () {
    Checkin::factory()->create([
        'source' => 'swarm',
        'source_id' => 'ancient',
        'occurred_at' => now()->subYears(3),
    ]);

    fakeSwarmRuns([[]]);

    $this->artisan('foursquare:sync')->assertSuccessful();

    Saloon::assertSent(fn ($request, $response) => (int) $request->query()->get('afterTimestamp') >= now()->subDays(91)->timestamp);
});

it('stores a newly returned check-in', function () {
    fakeSwarmRuns([[swarmItem('fresh', 'Flat white')]]);

    $this->artisan('foursquare:sync')->assertSuccessful();

    $checkin = Checkin::where('source_id', 'fresh')->first();
    expect($checkin)->not->toBeNull()
        ->and($checkin->venue_name)->toBe('Coffee Bar')
        ->and($checkin->city)->toBe('London')
        ->and($checkin->description)->toBe('Flat white');
});

/**
 * A check-in without its pin renders as a bare card, so the map is queued as
 * soon as the sync stores one rather than waiting for the next maps:generate
 * sweep. The overlap window re-sends check-ins already stored, which must not
 * queue a second job to redraw a map that is already there.
 */
it('queues the pin for a new check-in, but not for one seen again', function () {
    Queue::fake();

    fakeSwarmRuns([
        [swarmItem('same')],
        [swarmItem('same', 'Added later')],
    ]);

    $this->artisan('foursquare:sync')->assertSuccessful();
    Queue::assertPushed(GenerateEntryMap::class, 1);

    $this->artisan('foursquare:sync')->assertSuccessful();
    Queue::assertPushed(GenerateEntryMap::class, 1);
});

it('updates rather than duplicates a check-in seen again in the overlap window', function () {
    fakeSwarmRuns([
        [swarmItem('same')],
        [swarmItem('same', 'Added later')],
    ]);

    $this->artisan('foursquare:sync')->assertSuccessful();
    $this->artisan('foursquare:sync')->assertSuccessful();

    expect(Checkin::where('source_id', 'same')->count())->toBe(1)
        ->and(Checkin::where('source_id', 'same')->first()->description)->toBe('Added later');
});
