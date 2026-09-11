<?php

use App\DynamicTags\DynamicTagRegistry;
use App\Enums\DateFormat;
use App\Models\Flight;
use Carbon\CarbonImmutable;

it('renders each named format', function (string $format, string $expected) {
    $at = CarbonImmutable::parse('2003-02-19 11:20:00');

    expect(DateFormat::from($format)->apply($at))->toBe($expected);
})->with([
    ['date', '19 Feb 2003'],
    ['long', '19th February 2003'],
    ['month', 'February 2003'],
    ['day-month', '19 February'],
    ['year', '2003'],
    ['time', '11:20am'],
    ['datetime', 'Wed 19 Feb 2003, 11:20am'],
]);

it('resolves the earliest entry of a type', function () {
    Flight::factory()->create(['occurred_at' => '2003-02-19 11:20:00']);
    Flight::factory()->create(['occurred_at' => '2020-01-01 09:00:00']);

    expect(app(DynamicTagRegistry::class)->value('entries.first', ['type' => 'flight', 'format' => 'long'])['text'])
        ->toBe('19th February 2003');
});

it('resolves the most recent entry of a type', function () {
    Flight::factory()->create(['occurred_at' => '2003-02-19 11:20:00']);
    Flight::factory()->create(['occurred_at' => '2020-01-01 09:00:00']);

    expect(app(DynamicTagRegistry::class)->value('entries.latest', ['type' => 'flight', 'format' => 'year'])['text'])
        ->toBe('2020');
});

it('returns null when a type has no entries', function () {
    expect(app(DynamicTagRegistry::class)->value('entries.first', ['type' => 'flight']))->toBeNull();
});
