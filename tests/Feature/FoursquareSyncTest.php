<?php

use App\Models\Checkin;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.foursquare.access_token' => 'test-token']);
});

/**
 * Fake one page of check-ins per run, each followed by the empty page that
 * ends pagination. Pass one array per `foursquare:sync` invocation.
 *
 * Built as a single sequence rather than by calling `Http::fake()` per run:
 * a second `fake()` appends a stub instead of replacing the first, so the
 * exhausted sequence still matches and throws.
 *
 * @param  list<list<array<string, mixed>>>  $runs
 */
function fakeSwarmRuns(array $runs): void
{
    $sequence = Http::sequence();

    foreach ($runs as $items) {
        $sequence->push(['response' => ['checkins' => ['items' => $items]]]);
        $sequence->push(['response' => ['checkins' => ['items' => []]]]);
    }

    Http::fake(['*users/self/checkins*' => $sequence]);
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
    Http::assertSent(fn ($request) => (int) $request['afterTimestamp'] === now()->subDays(2)->timestamp);
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
    Http::assertSent(fn ($request) => (int) $request['afterTimestamp'] === $newest->timestamp);
});

it('caps the catch-up so a long gap does not refetch all history', function () {
    Checkin::factory()->create([
        'source' => 'swarm',
        'source_id' => 'ancient',
        'occurred_at' => now()->subYears(3),
    ]);

    fakeSwarmRuns([[]]);

    $this->artisan('foursquare:sync')->assertSuccessful();

    Http::assertSent(fn ($request) => (int) $request['afterTimestamp'] >= now()->subDays(91)->timestamp);
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
