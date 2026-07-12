<?php

use App\Content\EntryFileRepository;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Fuel;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->contentPath = storage_path('framework/testing/content-travel-'.uniqid('', true));
    File::ensureDirectoryExists($this->contentPath);
    config(['content.path' => $this->contentPath]);
});

afterEach(function () {
    if (isset($this->contentPath) && File::isDirectory($this->contentPath)) {
        File::deleteDirectory($this->contentPath);
    }
});

it('writes a checkin file from the venue slug', function () {
    $checkin = Checkin::factory()->create([
        'occurred_at' => '2026-04-01 12:00:00',
        'venue_name' => 'Borough Market',
        'city' => 'London',
        'description' => 'Lunch.',
    ]);

    $path = $this->contentPath.'/2026/04/01/borough-market.md';

    expect(File::exists($path))->toBeTrue()
        ->and($checkin->ulid)->not->toBeEmpty();

    $parsed = app(EntryFileRepository::class)->parse(File::get($path));

    expect($parsed['frontmatter']['type'])->toBe('checkin')
        ->and($parsed['frontmatter']['venue_name'])->toBe('Borough Market')
        ->and($parsed['body'])->toContain('Lunch.');
});

it('writes distinct fuel files when two stops share a day', function () {
    Fuel::factory()->create([
        'occurred_at' => '2026-04-02 09:00:00',
        'litres' => 40,
        'cost' => 50,
    ]);

    Fuel::factory()->create([
        'occurred_at' => '2026-04-02 18:00:00',
        'litres' => 20,
        'cost' => 25,
    ]);

    expect(File::exists($this->contentPath.'/2026/04/02/fuel.md'))->toBeTrue()
        ->and(File::exists($this->contentPath.'/2026/04/02/fuel-2.md'))->toBeTrue();
});

it('writes a flight file using the route slug', function () {
    $flight = Flight::factory()->create([
        'occurred_at' => '2026-04-03 06:00:00',
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
        'airline_icao' => 'BAW',
        'flight_number' => '117',
    ]);

    $path = $this->contentPath.'/2026/04/03/lhr-jfk.md';

    expect(File::exists($path))->toBeTrue();

    $parsed = app(EntryFileRepository::class)->parse(File::get($path));

    expect($parsed['frontmatter']['type'])->toBe('flight')
        ->and($parsed['frontmatter']['origin_iata'])->toBe('LHR')
        ->and($parsed['frontmatter']['destination_iata'])->toBe('JFK')
        ->and($parsed['frontmatter']['id'])->toBe($flight->ulid);
});
