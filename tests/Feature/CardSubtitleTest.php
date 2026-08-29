<?php

use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Sleep;
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

    expect($card['subtitle'])->toBe('I ran 1.6 mi in 22m and burned 210 kcal.');

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

    expect($card['subtitle'])->toBe('I did 22m of gym and burned 210 kcal.');

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

it('writes the flight subtitle as a sentence, with distance still a token', function () {
    $flight = Flight::factory()->create([
        'origin_iata' => 'FCO',
        'destination_iata' => 'BCN',
        'distance' => 482803, // ~300 mi
        'cabin_class' => 'economy',
    ]);

    $card = CardPresenter::for($flight)->toArray();

    expect($card['subtitle'])->toBe('I flew from FCO to BCN. It was 300 mi in economy.');

    $distanceToken = collect($card['subtitleTokens'])->firstWhere('t', 'dist');
    expect($distanceToken['m'])->toBe(482803)
        ->and($distanceToken['sep'])->toBe(' ');
});

it('closes the flight sentence without a cabin class', function () {
    $flight = Flight::factory()->create([
        'origin_iata' => 'FCO',
        'destination_iata' => 'BCN',
        'distance' => 482803, // ~300 mi
        'cabin_class' => null,
    ]);

    $card = CardPresenter::for($flight)->toArray();

    expect($card['subtitle'])->toBe('I flew from FCO to BCN. It was 300 mi.')
        ->and($card['subtitle'])->not->toContain(' in ');
});

it('writes the fuel subtitle as a sentence with a price clause', function () {
    $fuel = Fuel::factory()->create([
        'litres' => 33,
        'cost' => 45.06,
        'price_per_litre' => 1.359,
    ]);

    expect(CardPresenter::for($fuel)->toArray()['subtitle'])
        ->toBe('I filled up with 33.00 litres in '.$fuel->city.'. That was £1.359 a litre.');
});

it('drops the price sentence when there is no price per litre', function () {
    $fuel = Fuel::factory()->create([
        'litres' => 33,
        'cost' => 45.06,
        'price_per_litre' => null,
    ]);

    $subtitle = CardPresenter::for($fuel)->toArray()['subtitle'];

    expect($subtitle)->toBe('I filled up with 33.00 litres in '.$fuel->city.'.')
        ->and($subtitle)->not->toContain(' a litre');
});

it('uses the checkin note as its subtitle when present', function () {
    $checkin = Checkin::factory()->create([
        'description' => 'Great coffee here',
        'category' => 'Coffee Shop',
        'city' => 'London',
    ]);

    expect(CardPresenter::for($checkin)->toArray()['subtitle'])->toBe('Great coffee here');
});

// Foursquare's vocabulary includes Road, Platform and Town, so the category is
// shown as its own label rather than written into a sentence about the place.
it('leaves a checkin with no note unsubtitled, carrying its category as data', function () {
    $checkin = Checkin::factory()->create([
        'description' => null,
        'venue_name' => 'Blue Bottle',
        'category' => 'Coffee Shop',
        'city' => 'London',
        'address' => 'High Street',
    ]);

    $card = CardPresenter::for($checkin)->toArray();

    expect($card['title'])->toBe('at Blue Bottle')
        ->and($card['subtitle'])->toBeNull()
        ->and($card['meta']['category'])->toBe('Coffee Shop')
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

it('names a film by its leading genre and how long it ran', function () {
    $media = Media::factory()->create([
        'type' => 'film',
        'title' => 'Exit 8',
        'rating' => null,
        'meta' => [
            'year' => 2026,
            'runtime' => 95,
            // TMDB orders genres by relevance, so the first is the one to use.
            'tmdb' => ['genres' => ['Horror', 'Mystery']],
        ],
    ]);

    expect(CardPresenter::for($media)->toArray()['subtitle'])
        ->toBe('I watched this 2026 horror film. It was 95 minutes long.');
});

it('falls back to "film" when TMDB gave no genre', function () {
    $media = Media::factory()->create([
        'type' => 'film',
        'title' => 'Unknown',
        'rating' => null,
        'meta' => ['year' => 2026],
    ]);

    expect(CardPresenter::for($media)->toArray()['subtitle'])->toBe('I watched this 2026 film.');
});

// "9h 21m" is read out a letter at a time, so the link's accessible name spells
// the duration while the visible title stays compact.
it('spells the duration in the sleep card\'s accessible name', function () {
    $sleep = Sleep::factory()->create([
        'duration' => 33660, // 9h 21m
        'bedtime' => '2026-08-24 23:30:00',
        'wake_time' => '2026-08-25 08:51:00',
        'score' => 80,
    ]);

    $card = CardPresenter::for($sleep)->toArray();

    expect($card['title'])->toBe('I slept for 9h 21m')
        ->and($card['titleLabel'])->toBe('Sleep log, I slept for 9 hours 21 minutes');
});
