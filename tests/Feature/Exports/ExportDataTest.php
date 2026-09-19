<?php

use App\Data\Aspects\Geometry;
use App\Data\Aspects\Span;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportLink;
use App\Enums\TimelineType;
use Carbon\CarbonImmutable;

function exportFixture(array $overrides = []): ExportData
{
    return new ExportData(
        type: $overrides['type'] ?? TimelineType::Flight,
        url: 'https://example.test/2026/06/08/krk-lgw',
        title: 'Balice, PL to London, GB',
        summary: 'A flight.',
        occurred: null,
        fields: $overrides['fields'] ?? [ExportField::make('distance', 'Distance', '876 miles', 1409785)],
        links: $overrides['links'] ?? [ExportLink::make('tag', 'Tagged', 'poland', '/tags/poland', 'category')],
        body: null,
        aspects: $overrides['aspects'] ?? [],
    );
}

it('falls back raw to display and drops a blank optional field', function () {
    expect(ExportField::make('cabin', 'Cabin', 'Economy')->raw)->toBe('Economy')
        ->and(ExportField::maybe('cabin', 'Cabin', null))->toBeNull()
        ->and(ExportField::maybe('cabin', 'Cabin', ''))->toBeNull()
        ->and(ExportField::maybe('cabin', 'Cabin', 'Economy', 'economy')?->raw)->toBe('economy');
});

it('absolutises a relative link url', function () {
    config(['app.url' => 'https://example.test']);

    expect(ExportLink::make('tag', 'Tagged', 'poland', '/tags/poland')->url)
        ->toBe('https://example.test/tags/poland');
});

it('leaves an already absolute link url alone', function () {
    expect(ExportLink::make('source', 'On Strava', 'Strava', 'https://strava.com/x')->url)
        ->toBe('https://strava.com/x');
});

it('looks a field up by key and filters links by rel', function () {
    $data = exportFixture();

    expect($data->field('distance')?->display)->toBe('876 miles')
        ->and($data->field('nope'))->toBeNull()
        ->and($data->linksWithRel('category'))->toHaveCount(1)
        ->and($data->linksWithRel('syndication'))->toBeEmpty();
});

it('returns an aspect by class and null when absent', function () {
    $geometry = Geometry::point(51.5, -0.1);
    $data = exportFixture(['aspects' => [Geometry::class => $geometry]]);

    expect($data->aspect(Geometry::class))->toBe($geometry)
        ->and(exportFixture()->aspect(Geometry::class))->toBeNull();
});

it('serialises a field with both display and raw', function () {
    expect(ExportField::make('distance', 'Distance', '876 miles', 1409785)->toArray())
        ->toBe(['key' => 'distance', 'label' => 'Distance', 'display' => '876 miles', 'raw' => 1409785]);
});

it('stores a point as longitude then latitude, per GeoJSON', function () {
    expect(Geometry::point(51.5, -0.1)->toArray())
        ->toBe(['type' => 'Point', 'coordinates' => [-0.1, 51.5]]);
});

it('builds a line string from at least two points, flipping each to lng, lat', function () {
    $line = Geometry::lineString([[51.5, -0.1], [48.8, 2.3]]);

    expect($line?->toArray())->toBe([
        'type' => 'LineString',
        'coordinates' => [[-0.1, 51.5], [2.3, 48.8]],
    ]);
});

it('yields no line string for fewer than two points, so a one-point track has no geojson', function () {
    expect(Geometry::lineString([[51.5, -0.1]]))->toBeNull()
        ->and(Geometry::lineString([]))->toBeNull();
});

it('yields no moment span when the duration is null or non-positive', function () {
    $start = CarbonImmutable::parse('2026-06-08 10:00:00');

    expect(Span::moment($start, null, 'Europe/London'))->toBeNull()
        ->and(Span::moment($start, 0, 'Europe/London'))->toBeNull()
        ->and(Span::moment($start, -60, 'Europe/London'))->toBeNull()
        ->and(Span::moment($start, 3600, 'Europe/London'))->not->toBeNull();
});

it('yields no between span when there is no end, so an open entry has no ics', function () {
    $start = CarbonImmutable::parse('2026-06-08 10:00:00');

    expect(Span::between($start, null, 'Europe/London'))->toBeNull()
        ->and(Span::between($start, $start->addHour(), 'Europe/London'))->not->toBeNull();
});

it('hides fields and links from a locked entry, leaking only the header', function () {
    $data = new ExportData(
        type: TimelineType::Note,
        url: 'https://example.test/private-note',
        title: 'Private',
        summary: null,
        occurred: null,
        fields: [ExportField::make('secret', 'Secret', 'shhh')],
        links: [ExportLink::make('tag', 'Tagged', 'private', '/tags/private')],
        locked: true,
    );

    expect($data->toArray())->toBe([
        'type' => 'note',
        'url' => 'https://example.test/private-note',
        'title' => 'Private',
        'summary' => null,
        'occurred' => null,
        'locked' => true,
    ])->and($data->toArray())->not->toHaveKeys(['fields', 'links']);
});
