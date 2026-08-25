<?php

use App\Support\CityTimezones;

/*
 * The nearest-city heuristic can be wrong near a border, so these are the cases
 * that would catch it: an island against its mainland, two airports a trip's
 * zone gets wrong, and two land borders. All were verified against polygon
 * boundary data from timezone-boundary-builder.
 */

it('resolves a coordinate to the zone it is actually in', function (string $place, float $latitude, float $longitude, string $expected) {
    expect(app(CityTimezones::class)->forCoordinate($latitude, $longitude))->toBe($expected);
})->with([
    // The check-in that started this: Spain by country, but an hour off mainland.
    ['La Bahia, Lanzarote', 28.858452843247, -13.842655426922, 'Atlantic/Canary'],
    ['Madrid', 40.4168, -3.7038, 'Europe/Madrid'],
    // Both sat on a trip whose destination zone was an hour out.
    ['Athens airport', 37.9364, 23.9445, 'Europe/Athens'],
    ["Chicago O'Hare", 41.9742, -87.9073, 'America/Chicago'],
    ['Spain/Portugal border', 41.9, -6.75, 'Europe/Lisbon'],
    ['London', 51.5072, -0.1276, 'Europe/London'],
    ['New York', 40.75, -73.99, 'America/New_York'],
    ['Basel', 47.5596, 7.5886, 'Europe/Zurich'],
]);

it('still answers somewhere remote with no city nearby', function () {
    // Nothing within the usual span, so the wider one applies.
    expect(app(CityTimezones::class)->forCoordinate(-25.0, 132.0))->toBe('Australia/Darwin');
});

// Guessing a zone from the nearest land hundreds of miles away would be worse
// than saying nothing: null already means home, and no entry here is at sea.
it('gives up rather than guessing far out at sea', function () {
    expect(app(CityTimezones::class)->forCoordinate(40.0, -40.0))->toBeNull();
});

it('returns a zone PHP recognises for every row it holds', function () {
    $zones = collect(file(app(CityTimezones::class)->path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        ->map(fn (string $line): string => explode(',', $line, 3)[2] ?? '')
        ->unique();

    $known = timezone_identifiers_list(DateTimeZone::ALL_WITH_BC);

    expect($zones)->not->toBeEmpty()
        ->and($zones->reject(fn (string $zone): bool => in_array($zone, $known, true))->all())->toBe([]);
});
