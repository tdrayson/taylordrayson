<?php

use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Presenters\CardPresenter;

it('joins activity distance and duration with "in" and keeps calories comma-joined', function () {
    $activity = Activity::factory()->create([
        'type' => 'run',
        'distance' => 2574, // ~1.6 mi
        'duration' => 1320, // 22m
        'calories' => 210,
        'meta' => [],
    ]);

    $card = CardPresenter::for($activity)->toArray();

    expect($card['subtitle'])->toContain(' in ')
        ->and($card['subtitle'])->toMatch('/mi in .*, 210 kcal/');

    $durationToken = collect($card['subtitleTokens'])->first(fn (array $token): bool => $token['t'] === 'text' && str_contains($token['v'], 'm'));
    expect($durationToken['sep'])->toBe(' in ');
});

it('does not lead activity subtitle with "in" when there is no distance', function () {
    $activity = Activity::factory()->create([
        'type' => 'gym',
        'distance' => null,
        'duration' => 1320,
        'calories' => 210,
        'meta' => [],
    ]);

    $card = CardPresenter::for($activity)->toArray();

    expect($card['subtitle'])->not->toContain(' in ')
        ->and($card['subtitle'])->toBe('22m, 210 kcal');

    $durationToken = $card['subtitleTokens'][0];
    expect($durationToken['t'])->toBe('text')
        ->and($durationToken)->not->toHaveKey('sep');
});

it('activity subtitle shows just distance when duration and calories are absent', function () {
    $activity = Activity::factory()->create([
        'type' => 'run',
        'distance' => 2574,
        'duration' => 0,
        'calories' => 0,
        'meta' => [],
    ]);

    expect(CardPresenter::for($activity)->toArray()['subtitle'])->toBe('1.6 mi');
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

it('builds the fuel subtitle with "for" and "at" clauses', function () {
    $fuel = Fuel::factory()->create([
        'litres' => 33,
        'cost' => 45.06,
        'price_per_litre' => 1.359,
    ]);

    expect(CardPresenter::for($fuel)->toArray()['subtitle'])->toBe('33 L for £45.06 at £1.359/L');
});

it('omits the "at" clause when fuel has no price per litre', function () {
    $fuel = Fuel::factory()->create([
        'litres' => 33,
        'cost' => 45.06,
        'price_per_litre' => null,
    ]);

    $subtitle = CardPresenter::for($fuel)->toArray()['subtitle'];

    expect($subtitle)->toBe('33 L for £45.06')
        ->and($subtitle)->not->toContain(' at ');
});

it('joins checkin category and city with "in"', function () {
    $checkin = Checkin::factory()->create([
        'category' => 'Coffee Shop',
        'city' => 'London',
    ]);

    expect(CardPresenter::for($checkin)->toArray()['subtitle'])->toBe('Coffee Shop in London');
});

it('does not dangle "in" when a checkin has only a category', function () {
    $checkin = Checkin::factory()->create([
        'category' => 'Coffee Shop',
        'city' => null,
    ]);

    $subtitle = CardPresenter::for($checkin)->toArray()['subtitle'];

    expect($subtitle)->toBe('Coffee Shop')
        ->and($subtitle)->not->toContain(' in ');
});

it('joins event venue and city with "in"', function () {
    $event = Event::factory()->create([
        'venue_name' => 'The Roundhouse',
        'city' => 'London',
    ]);

    expect(CardPresenter::for($event)->toArray()['subtitle'])->toBe('The Roundhouse in London');
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
