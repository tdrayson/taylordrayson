<?php

use App\Support\Units;

it('parses durations to seconds', function (mixed $input, ?int $expected) {
    expect(Units::seconds($input))->toBe($expected);
})->with([
    'plain int' => [8700, 8700],
    'numeric string' => ['8700', 8700],
    'hours and minutes' => ['2h 56m', 10560],
    'compact' => ['2h56m', 10560],
    'minutes only' => ['45m', 2700],
    'minutes word' => ['90 min', 5400],
    'hours decimal' => ['1.5h', 5400],
    'clock hms' => ['2:56:00', 10560],
    'clock ms' => ['56:30', 3390],
    'seconds suffix' => ['30s', 30],
    'garbage' => ['soon', null],
    'null' => [null, null],
    'mph rejected' => ['5 mph', null],
    'ms rejected' => ['500 ms', null],
    'hz rejected' => ['5 hz', null],
    'trailing garbage rejected' => ['2hx', null],
    'compound unit rejected' => ['774 miles per hour', null],
]);

it('parses distances to metres', function (mixed $input, ?int $expected) {
    expect(Units::metres($input))->toBe($expected);
})->with([
    'plain int metres' => [5230, 5230],
    'numeric string' => ['5230', 5230],
    'km' => ['5.2 km', 5200],
    'km compact' => ['5.2km', 5200],
    'miles' => ['774 miles', 1245632],
    'mi' => ['774 mi', 1245632],
    'metres suffix' => ['1200 m', 1200],
    'garbage' => ['far away', null],
    'null' => [null, null],
]);
