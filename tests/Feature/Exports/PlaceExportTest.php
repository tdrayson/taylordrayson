<?php

use App\Models\Place;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes a place check-in as labelled fields in order', function () {
    $place = Place::factory()->create([
        'occurred_at' => '2026-09-13 10:00:00',
        'venue_name' => 'Costa Coffee',
        'type' => 'coffee-shop',
        'address' => '12 High Street',
        'city' => 'Croydon',
        'postcode' => 'CR0 1AB',
        'country' => 'United Kingdom',
        'event_name' => null,
        'latitude' => 51.376,
        'longitude' => -0.098,
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($place);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['venue', 'category', 'location'])
        ->and($export->field('venue')->display)->toBe('Costa Coffee')
        ->and($export->field('category')->display)->toBe('Coffee Shop')
        ->and($export->field('category')->raw)->toBe('coffee-shop')
        ->and($export->field('location')->display)->toBe('12 High Street, Croydon, CR0 1AB, United Kingdom');

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('category', 'type', 'day')
        ->and($export->links[0]->key)->toBe('category')
        ->and($export->links[0]->url)->toEndWith('/places/coffee-shop');
});

it('offers geojson for a place only when it was located', function () {
    $located = Place::factory()->create(['occurred_at' => '2026-09-13 10:00:00', 'latitude' => 51.376, 'longitude' => -0.098, 'status' => 'published']);
    $unlocated = Place::factory()->create(['occurred_at' => '2026-09-13 10:00:00', 'latitude' => null, 'longitude' => null, 'status' => 'published']);

    $locatedFormats = array_keys(Formats::for(ExportPresenter::for($located)));
    $unlocatedFormats = array_keys(Formats::for(ExportPresenter::for($unlocated)));

    expect($locatedFormats)->toContain('geojson')
        ->and($unlocatedFormats)->not->toContain('geojson');
});

it('never leaks an id, a timestamp or a password', function () {
    $place = Place::factory()->create(['occurred_at' => '2026-09-13 10:00:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($place)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
