<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Rules\ExistsOnModel;
use App\Support\LookupCsv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Tests\Fixtures\LookupFixture;

it('loads the real airline and airport rows from the canonical csvs', function () {
    $airlines = LookupCsv::from(database_path('lookups/airlines.csv'));
    $airports = LookupCsv::from(database_path('lookups/airports.csv'));

    expect(count($airlines))->toBeGreaterThan(5000)
        ->and(count($airports))->toBeGreaterThan(9000)
        ->and(collect($airlines)->firstWhere('icao_code', 'BAW')['name'] ?? null)->toBe('British Airways')
        ->and(collect($airports)->firstWhere('iata_code', 'LHR')['city'] ?? null)->toBe('London');
});

/**
 * Regression: HasLookupCsv recreates `airlines_icao_code_unique` /
 * `airports_iata_code_unique` on the cached table, but only on the with-data path,
 * so nothing enforces them under test. A CSV edit introducing a duplicate key would
 * therefore fail at cache-build time on every dev machine and in production rather
 * than here. This test reads the real CSVs directly so it fails first instead.
 */
it('has no duplicate icao_code or iata_code keys in the canonical csvs', function () {
    $airlines = collect(LookupCsv::from(database_path('lookups/airlines.csv')));
    $airports = collect(LookupCsv::from(database_path('lookups/airports.csv')));

    $airlineIcaoCodes = $airlines->pluck('icao_code')->filter();
    $airportIataCodes = $airports->pluck('iata_code')->filter();

    expect($airlineIcaoCodes->count())->toBe(
        $airlineIcaoCodes->unique()->count(),
        'Duplicate airline icao_code values would make flights resolve to the wrong airline (belongsTo is last-one-wins).'
    )->and($airportIataCodes->count())->toBe(
        $airportIataCodes->unique()->count(),
        'Duplicate airport iata_code values would make flights resolve to the wrong airport (belongsTo is last-one-wins).'
    );
});

it('starts with empty lookup tables under test', function () {
    expect(Airline::count())->toBe(0)
        ->and(Airport::count())->toBe(0);
});

it('resolves flight airline and airport relations across the sushi connection', function () {
    Airline::factory()->create(['icao_code' => 'BAW', 'iata_code' => 'BA', 'name' => 'British Airways']);
    Airport::factory()->create(['iata_code' => 'LHR', 'name' => 'Heathrow', 'city' => 'London', 'country' => 'GB']);
    Airport::factory()->create(['iata_code' => 'JFK', 'name' => 'JFK', 'city' => 'New York', 'country' => 'US']);

    $flight = Flight::factory()->create([
        'airline_icao' => 'BAW',
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
    ]);

    $flight->load(['airline', 'origin', 'destination']);

    expect($flight->airline?->name)->toBe('British Airways')
        ->and($flight->origin?->iata_code)->toBe('LHR')
        ->and($flight->destination?->city)->toBe('New York')
        ->and($flight->origin?->place)->toBe('London, GB');
});

/**
 * Regression: Airline model must NOT cache under test. A cached empty row set
 * would poison the shared Sushi cache file, causing later dev requests to serve
 * no airlines even when the CSV is unchanged.
 */
it('disables airline caching under test to prevent cache poisoning', function () {
    $airline = new Airline;
    $reflection = new ReflectionMethod($airline, 'sushiShouldCache');
    $reflection->setAccessible(true);

    expect($reflection->invoke($airline))->toBeFalse('Airline must not cache in test environment')
        ->and($airline->getRows())->toBe([], 'Airline must return empty rows in test environment');
});

/**
 * Regression: Airport model must NOT cache under test. A cached empty row set
 * would poison the shared Sushi cache file, causing later dev requests to serve
 * no airports even when the CSV is unchanged.
 */
it('disables airport caching under test to prevent cache poisoning', function () {
    $airport = new Airport;
    $reflection = new ReflectionMethod($airport, 'sushiShouldCache');
    $reflection->setAccessible(true);

    expect($reflection->invoke($airport))->toBeFalse('Airport must not cache in test environment')
        ->and($airport->getRows())->toBe([], 'Airport must return empty rows in test environment');
});

/**
 * Regression: ExistsOnModel must humanize the attribute name in its error message
 * the same way Laravel's built-in `exists:` rule does. A previous implementation
 * interpolated the raw attribute name (e.g. "airline_icao"), producing a message
 * that read differently from every other field's validation error in the same
 * response. Asserting only the error key would not have caught this.
 */
it('humanizes the attribute name in the exists on model error message', function () {
    $validator = validator(
        ['airline_icao' => 'ZZZ'],
        ['airline_icao' => [new ExistsOnModel(Airline::class, 'icao_code')]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('airline_icao'))->toBe('The selected airline icao is invalid.');
});

/**
 * Regression for #474: a factory or create() call outside the testing environment
 * inserts straight into the shared Sushi cache file, where the row persists as real
 * lookup data. Kraków spent a while in Korea that way.
 */
it('refuses to write lookup rows outside the testing environment', function (string $model) {
    app()['env'] = 'local';

    expect(fn () => $model::factory()->create())
        ->toThrow(LogicException::class, 'read-only lookup');
})->with([[Airline::class], [Airport::class]]);

it('refuses to update or delete a lookup row outside the testing environment', function () {
    $airport = Airport::factory()->create(['iata_code' => 'LHR']);

    app()['env'] = 'local';

    expect(fn () => $airport->update(['country' => 'ZZ']))
        ->toThrow(LogicException::class, 'read-only lookup')
        ->and(fn () => $airport->delete())
        ->toThrow(LogicException::class, 'read-only lookup');
});

/**
 * The unique index only protects the lookup if it covers the column the relation
 * actually resolves on, which for Airline is ICAO rather than the non-unique IATA.
 */
it('holds the column each flight relation resolves on unique', function (string $relation, string $model, string $expected) {
    $key = (new ReflectionMethod($model, 'lookupKey'))->invoke(new $model);

    expect((new Flight)->{$relation}()->getOwnerKeyName())->toBe($key)
        ->and($key)->toBe($expected);
})->with([
    ['airline', Airline::class, 'icao_code'],
    ['origin', Airport::class, 'iata_code'],
    ['destination', Airport::class, 'iata_code'],
]);

/**
 * The guards that matter most cannot be reached through Airport or Airline,
 * which load no rows under test and so take Sushi's empty-table path. This
 * builds the cached table the way production does and asserts what lands.
 */
it('builds the cached lookup table with a unique key that rejects duplicates', function () {
    $cacheDirectory = sys_get_temp_dir().'/sushi-'.Str::random(8);
    mkdir($cacheDirectory);
    config(['sushi.cache-path' => $cacheDirectory]);

    expect(LookupFixture::count())->toBe(2);

    $indexes = collect(LookupFixture::resolveConnection()->select(
        "select name from sqlite_master where type = 'index' and tbl_name = 'lookup_fixtures'"
    ))->pluck('name');

    expect($indexes)->toContain('lookup_fixtures_code_unique');

    // A query-builder insert fires no model event, so the index is the only
    // thing standing between it and a duplicate lookup key.
    expect(fn () => LookupFixture::query()->insert(['code' => 'AAA', 'name' => 'Leaked']))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and(LookupFixture::count())->toBe(2);
});

/**
 * The cache file name carries the table definition, so a machine holding a
 * pre-#474 cache looks for a file that cannot exist and rebuilds, rather than
 * serving warm rows that never got the unique index.
 */
it('names the cache file after the table definition', function (string $model, string $expected) {
    $name = (new ReflectionMethod($model, 'sushiCacheFileName'))->invoke(new $model);

    expect($name)->toStartWith($expected)
        ->and($name)->toMatch('/-[0-9a-f]{8}\\.sqlite$/');
})->with([
    [Airport::class, 'sushi-app-models-airport-'],
    [Airline::class, 'sushi-app-models-airline-'],
]);
