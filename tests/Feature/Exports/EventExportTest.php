<?php

use App\Models\Event;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes an event as labelled fields in order', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2026-09-13 19:30:00',
        'ends_at' => '2026-09-13 22:00:00',
        'all_day' => false,
        'name' => 'Arctic Monkeys',
        'organiser' => 'AEG Presents',
        'venue_name' => 'O2 Arena',
        'address' => 'Peninsula Square',
        'city' => 'London',
        'country' => 'United Kingdom',
        'url' => 'https://example.com/tickets',
        'latitude' => 51.503,
        'longitude' => 0.003,
        'timezone' => 'Europe/London',
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($event);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['event', 'venue', 'location', 'organiser', 'ends'])
        ->and($export->field('event')->display)->toBe('Arctic Monkeys')
        ->and($export->field('venue')->display)->toBe('O2 Arena')
        ->and($export->field('location')->display)->toBe('Peninsula Square, London, United Kingdom')
        ->and($export->field('organiser')->display)->toBe('AEG Presents')
        ->and($export->field('ends')->display)->toBe('13 September 2026 at 22:00');

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('tickets', 'type', 'day')
        ->and($export->links[0]->key)->toBe('tickets');
});

it('offers ics and geojson for a located, timed event', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2026-09-13 19:30:00', 'ends_at' => '2026-09-13 22:00:00',
        'latitude' => 51.503, 'longitude' => 0.003, 'status' => 'published',
    ]);

    $available = array_keys(Formats::for(ExportPresenter::for($event)));

    expect($available)->toContain('ics', 'geojson');
});

it('offers no geojson for an event with no venue location', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2026-09-13 19:30:00', 'ends_at' => '2026-09-13 22:00:00',
        'latitude' => null, 'longitude' => null, 'status' => 'published',
    ]);

    $available = array_keys(Formats::for(ExportPresenter::for($event)));

    expect($available)->toContain('ics')->not->toContain('geojson');
});

it('never leaks an id, a timestamp or a password', function () {
    $event = Event::factory()->create(['occurred_at' => '2026-09-13 19:30:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($event)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
