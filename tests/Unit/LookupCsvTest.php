<?php

use App\Support\LookupCsv;

it('parses a csv into rows keyed by header', function () {
    $path = tempnam(sys_get_temp_dir(), 'lookup').'.csv';
    file_put_contents($path, "iata_code,name,city\nLHR,\"London Heathrow Airport\",London\nJFK,\"John F Kennedy\",\"New York\"\n");

    $rows = LookupCsv::from($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['iata_code' => 'LHR', 'name' => 'London Heathrow Airport', 'city' => 'London'])
        ->and($rows[1]['city'])->toBe('New York');

    @unlink($path);
});

it('converts empty cells to null', function () {
    $path = tempnam(sys_get_temp_dir(), 'lookup').'.csv';
    file_put_contents($path, "iata_code,icao_code,name\nAAA,,Anaa\n");

    $rows = LookupCsv::from($path);

    expect($rows[0]['icao_code'])->toBeNull()
        ->and($rows[0]['name'])->toBe('Anaa');

    @unlink($path);
});

it('throws when the file is missing rather than returning an empty set', function () {
    LookupCsv::from('/nonexistent/path/airports.csv');
})->throws(RuntimeException::class, 'Lookup CSV not found');

it('returns an empty list for a header-only file', function () {
    $path = tempnam(sys_get_temp_dir(), 'lookup').'.csv';
    file_put_contents($path, "iata_code,name\n");

    expect(LookupCsv::from($path))->toBe([]);

    @unlink($path);
});
