<?php

use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Presenters\CardPresenter;

it('writes an activity subtitle as a sentence, with distance still a token', function () {
    $activity = Activity::factory()->create([
        'type' => 'run',
        'distance' => 2574, // ~1.6 mi
        'duration' => 1320, // 22m
        'calories' => 210,
        'meta' => [],
    ]);

    $card = CardPresenter::for($activity)->toArray();

    expect($card['subtitle'])->toBe('I ran 1.6 mi in 22m, burning 210 kcal.');

    // Distance stays a raw-metres token so the mi/km toggle can rewrite it in
    // place; the sentence around it is plain text.
    $distanceToken = collect($card['subtitleTokens'])->firstWhere('t', 'dist');
    expect($distanceToken['m'])->toBe(2574)
        ->and($distanceToken['sep'])->toBe(' ');
});

it('names the activity instead of its distance when it has none', function () {
    $activity = Activity::factory()->create([
        'type' => 'gym',
        'distance' => null,
        'duration' => 1320,
        'calories' => 210,
        'meta' => [],
    ]);

    $card = CardPresenter::for($activity)->toArray();

    expect($card['subtitle'])->toBe('I did 22m of gym, burning 210 kcal.');

    $opening = $card['subtitleTokens'][0];
    expect($opening['t'])->toBe('text')
        ->and($opening)->not->toHaveKey('sep');
});

it('drops the trailing clauses when duration and calories are absent', function () {
    $activity = Activity::factory()->create([
        'type' => 'run',
        'distance' => 2574,
        'duration' => 0,
        'calories' => 0,
        'meta' => [],
    ]);

    expect(CardPresenter::for($activity)->toArray()['subtitle'])->toBe('I ran 1.6 mi.');
});

it('joins flight distance and cabin class with "in"', function () {
    $flight = Flight::factory()->create([
        'distance' => 482803, // ~300 mi
        'cabin_class' => 'economy',
    ]);

    $card = CardPresenter::for($flight)->toArray();

    expect($card['subtitle'])->toContain(' in economy');

    $cabinToken = collect($card['subtitleTokens'])->firstWhere('v', 'economy');
    expect($cabinToken['sep'])->toBe(' in ');
});

it('does not dangle "in" when a flight has no cabin class', function () {
    $flight = Flight::factory()->create([
        'distance' => 482803, // ~300 mi
        'cabin_class' => null,
    ]);

    $card = CardPresenter::for($flight)->toArray();

    expect($card['subtitle'])->toBe('300 mi')
        ->and($card['subtitle'])->not->toContain(' in ')
        ->and($card['subtitle'])->not->toMatch('/\s$/');

    expect($card['subtitleTokens'])->toHaveCount(1);
    expect($card['subtitleTokens'][0])->not->toHaveKey('sep');
});

it('writes the fuel subtitle as a sentence with a price clause', function () {
    $fuel = Fuel::factory()->create([
        'litres' => 33,
        'cost' => 45.06,
        'price_per_litre' => 1.359,
    ]);

    expect(CardPresenter::for($fuel)->toArray()['subtitle'])
        ->toBe('I filled up with 33.00 litres at £1.359 a litre, in '.$fuel->city.'.');
});

// Without a price clause between them, a comma would separate the verb from
// its own place: "33 litres, in Grimsby".
it('drops the price clause and its comma when there is no price per litre', function () {
    $fuel = Fuel::factory()->create([
        'litres' => 33,
        'cost' => 45.06,
        'price_per_litre' => null,
    ]);

    $subtitle = CardPresenter::for($fuel)->toArray()['subtitle'];

    expect($subtitle)->toBe('I filled up with 33.00 litres in '.$fuel->city.'.')
        ->and($subtitle)->not->toContain(' a litre')
        ->and($subtitle)->not->toContain(', in ');
});

it('uses the checkin note as its subtitle when present', function () {
    $checkin = Checkin::factory()->create([
        'description' => 'Great coffee here',
        'category' => 'Coffee Shop',
        'city' => 'London',
    ]);

    expect(CardPresenter::for($checkin)->toArray()['subtitle'])->toBe('Great coffee here');
});

it('captions a checkin with its category and city when it has no note', function () {
    $checkin = Checkin::factory()->create([
        'description' => null,
        'category' => 'Coffee Shop',
        'city' => 'London',
        'address' => 'High Street',
    ]);

    $card = CardPresenter::for($checkin)->toArray();

    expect($card['subtitle'])->toBe('A Coffee Shop in London.')
        ->and($card['meta']['address'])->toContain('London');
});

it('writes an event subtitle as a sentence naming venue and city', function () {
    $event = Event::factory()->create([
        'venue_name' => 'The Roundhouse',
        'city' => 'London',
    ]);

    expect(CardPresenter::for($event)->toArray()['subtitle'])->toBe('I went to The Roundhouse in London.');
});

it('keeps the calorie subtitle comma-joined with no connectives', function () {
    $calorie = Calorie::factory()->create([
        'occurred_at' => '2026-07-19 12:00:00',
        'protein' => 30,
        'carbs' => 40,
        'fat' => 10,
    ]);

    $subtitle = CardPresenter::for($calorie)->toArray()['subtitle'];

    expect($subtitle)->toContain(',')
        ->and($subtitle)->not->toContain(' in ');
});
