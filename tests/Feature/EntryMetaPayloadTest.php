<?php

use App\Models\Activity;
use App\Models\Event;
use App\Models\Flight;
use App\Support\EntryMeta;
use Illuminate\Database\Eloquent\Model;

it('keeps a flight booking reference off the page', function () {
    // A PNR plus a surname opens the booking on many airline sites. It was
    // reaching the browser inside the serialised meta bag, unread by the UI
    // but plainly visible in the page source.
    $flight = Flight::factory()->create(['meta' => [
        'aircraft' => 'Airbus A320',
        'seat' => '4F',
        'pnr' => 'L3JIDU',
        'callsign' => 'BA2820',
    ]]);

    $published = EntryMeta::published($flight);

    expect($published)->toBe(['aircraft' => 'Airbus A320', 'seat' => '4F']);
});

it('keeps ticket order numbers and prices off an event page', function () {
    $event = Event::factory()->create(['meta' => [
        'seat' => 'Rear Stalls, HH, 22',
        'order_no' => '17187933',
        'price' => '£18.75',
        'place_id' => 'ChIJ3TUCCagEdkgRsUc9IZZheiU',
    ]]);

    expect(EntryMeta::published($event))->toBe(['seat' => 'Rear Stalls, HH, 22']);
});

it('publishes only the activity fields the page renders', function () {
    $activity = Activity::factory()->create(['meta' => [
        'polyline' => 'abc123',
        'total_elevation_gain' => 10.5,
        'gear' => 'Btwin Triban 500',
        'average_watts' => 33,
    ]]);

    expect(EntryMeta::published($activity))
        ->toBe(['polyline' => 'abc123', 'total_elevation_gain' => 10.5]);
});

it('publishes nothing for a type nobody registered', function () {
    // The point of the whole change: a model, or a key, that nobody thought
    // about publishes nothing rather than everything.
    $unregistered = new class extends Model
    {
        protected $attributes = ['meta' => '{"secret":"value"}'];
    };

    expect(EntryMeta::published($unregistered))->toBe([]);
});

it('does not ship the stream arrays in the initial payload', function () {
    // These are excluded from the payload and sent separately as a deferred
    // prop, because they are large. They were being shipped anyway inside the
    // `timeline_entry.timelineable` copy of the model, which no exclusion
    // above it could reach.
    $activity = Activity::factory()->create([
        'occurred_at' => '2024-05-04 09:00:00',
        'heart_rate' => [101, 102, 103],
        'altitude' => [11, 12],
    ]);

    $content = $this->get($activity->url())->getContent();

    expect($content)->not->toContain('101,102,103')
        ->and($content)->not->toContain('timeline_entry');
});

it('does not serialise the booking reference into the entry page', function () {
    $flight = Flight::factory()->create([
        'occurred_at' => '2024-05-04 09:00:00',
        'meta' => ['aircraft' => 'Airbus A320', 'pnr' => 'L3JIDU'],
    ]);

    $response = $this->get($flight->url());

    $response->assertOk();
    expect($response->getContent())->not->toContain('L3JIDU')
        ->and($response->getContent())->toContain('Airbus A320');
});
