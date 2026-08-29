<?php

use App\Enums\MediaType;
use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Media;
use App\Models\Note;
use App\Models\Sleep;
use App\Presenters\CardPresenter;
use App\Support\Distance;
use App\Support\YouTube;

/**
 * Pins the exact array CardData::toArray() emits per type: every key, the order
 * they appear in, and which of them are omitted rather than null.
 *
 * Originally a parity guard proving the DTO reproduced the pre-refactor card()
 * arrays byte-for-byte. That premise ended when the card copy was rewritten;
 * what still earns its keep is the shape, so values that are computed rather
 * than authored (Distance::miles(), the model's own helpers) stay computed
 * here rather than hardcoded.
 */
it('reproduces the pre-refactor activity card shape', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-01-01 10:00:00',
        'type' => 'run',
        'name' => 'Morning Run',
        'distance' => 5000,
        'duration' => 1800,
        'calories' => 350,
        'meta' => ['elevation_gain' => 50],
    ]);

    expect(CardPresenter::for($activity)->toArray())->toEqual([
        'type' => 'activity',
        'icon' => 'footprints',
        'title' => 'Morning Run',
        'subtitle' => 'I ran '.Distance::miles(5000, 1).' mi in 30m and burned 350 kcal.',
        'subtitleTokens' => [
            ['t' => 'text', 'v' => 'I ran'],
            ['t' => 'dist', 'm' => 5000, 'p' => 1, 'sep' => ' '],
            ['t' => 'text', 'v' => 'in 30m', 'sep' => ' '],
            ['t' => 'text', 'v' => 'and burned 350 kcal', 'sep' => ' '],
            ['t' => 'text', 'v' => '.', 'sep' => ''],
        ],
        'occurred_at' => $activity->occurred_at,
        'accent' => 'activity',
        'meta' => ['polyline' => null, 'photos' => [], 'map' => null, 'mapDark' => null],
    ]);
});

it('reproduces the pre-refactor flight card shape', function () {
    $flight = Flight::factory()->create([
        'occurred_at' => '2026-01-01 09:30:00',
        'flight_number' => '8821',
        'airline_icao' => 'EZY',
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
        'distance' => 1609344,
        'duration' => 26700,
        'cabin_class' => 'economy',
        'departure_timezone' => null,
        'arrival_timezone' => null,
        'meta' => [],
    ]);

    expect(CardPresenter::for($flight)->toArray())->toEqual([
        'type' => 'flight',
        'icon' => 'plane',
        'title' => 'LHR → JFK',
        'subtitle' => 'I flew from LHR to JFK. It was 1,000 mi in economy.',
        'subtitleTokens' => [
            ['t' => 'text', 'v' => 'I flew from LHR to JFK. It was'],
            ['t' => 'dist', 'm' => 1609344, 'p' => 0, 'sep' => ' '],
            ['t' => 'text', 'v' => 'in economy', 'sep' => ' '],
            ['t' => 'text', 'v' => '.', 'sep' => ''],
        ],
        'occurred_at' => $flight->occurred_at,
        'accent' => 'flight',
        'meta' => [
            'route' => [
                'origin' => ['iata' => 'LHR', 'place' => null, 'name' => null, 'lat' => null, 'lng' => null],
                'destination' => ['iata' => 'JFK', 'place' => null, 'name' => null, 'lat' => null, 'lng' => null],
                'depart' => '2026-01-01T09:30',
                'arrive' => null,
                'distance' => Distance::miles(1609344),
                'duration' => 26700,
                'airline' => null,
            ],
            'map' => null,
            'mapDark' => null,
        ],
    ]);
});

it('reproduces the pre-refactor media card shape', function () {
    $media = Media::factory()->create([
        'occurred_at' => '2026-01-01 20:00:00',
        'type' => MediaType::Film,
        'title' => 'Interstellar',
        'rating' => 9,
        'meta' => ['year' => 2014],
    ]);

    expect(CardPresenter::for($media)->toArray())->toEqual([
        'type' => 'media',
        'icon' => 'film',
        'title' => 'Interstellar',
        'subtitle' => 'I watched this 2014 film and rated it 9/10.',
        'occurred_at' => $media->occurred_at,
        'accent' => 'media',
        'meta' => ['backdrop' => null],
    ]);
});

it('reproduces the pre-refactor note card shape', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2026-01-01 08:00:00',
        'content' => 'A short note about today.',
    ]);

    expect(CardPresenter::for($note)->toArray())->toEqual([
        'type' => 'note',
        'icon' => 'message-circle',
        'title' => 'A short note about today.',
        'subtitle' => null,
        'occurred_at' => $note->occurred_at,
        'accent' => 'note',
        'meta' => ['body' => 'A short note about today.', 'photos' => []],
    ]);
});

it('reproduces the pre-refactor single-day event card shape', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2026-01-01 19:00:00',
        'ends_at' => null,
        'name' => 'Test Gig',
        'venue_name' => 'Some Venue',
        'city' => 'London',
    ]);

    expect(CardPresenter::for($event)->toArray())->toEqual([
        'type' => 'event',
        'icon' => 'music',
        'title' => 'Test Gig',
        'subtitle' => 'I went to Some Venue in London.',
        'occurred_at' => $event->occurred_at,
        'accent' => 'event',
        'meta' => ['photos' => [], 'map' => null, 'mapDark' => null],
    ]);
});

it('reproduces the pre-refactor sleep card shape', function () {
    $sleep = Sleep::factory()->create([
        'occurred_at' => '2026-01-01 00:00:00',
        'bedtime' => '2025-12-31 23:00:00',
        'wake_time' => '2026-01-01 07:00:00',
        'duration' => 28800,
        'awake' => 600,
        'rem' => 6000,
        'core' => 15000,
        'deep' => 7200,
    ]);

    expect(CardPresenter::for($sleep)->toArray())->toEqual([
        'type' => 'sleep',
        'icon' => 'bed',
        'title' => 'I slept for 8h',
        'titleLabel' => 'Sleep log, I slept for 8 hours',
        'subtitle' => 'I went to bed at 11:00pm and woke at 7:00am.',
        'occurred_at' => $sleep->occurred_at,
        'accent' => 'sleep',
        'meta' => [
            'segments' => [
                ['label' => 'Awake', 'stage' => 'awake', 'seconds' => 600],
                ['label' => 'REM', 'stage' => 'rem', 'seconds' => 6000],
                ['label' => 'Light', 'stage' => 'light', 'seconds' => 15000],
                ['label' => 'Deep', 'stage' => 'deep', 'seconds' => 7200],
            ],
        ],
    ]);
});

it('reproduces the pre-refactor appearance card shape', function () {
    $appearance = Appearance::factory()->create([
        'occurred_at' => '2026-01-01 12:00:00',
        'title' => 'Building a Lifelog',
        'show_name' => 'Laracon EU',
        'video_url' => 'https://www.youtube.com/watch?v=W7rO_mZTuWM',
        'audio_url' => null,
        'duration' => 1800,
    ]);

    expect(CardPresenter::for($appearance)->toArray())->toEqual([
        'type' => 'appearance',
        'icon' => 'mic',
        'title' => 'Building a Lifelog',
        'subtitle' => 'I spoke at Laracon EU.',
        'occurred_at' => $appearance->occurred_at,
        'accent' => 'appearance',
        'meta' => [
            'media' => [
                'id' => "appearance-{$appearance->id}",
                'title' => 'Building a Lifelog',
                'audioUrl' => null,
                'videoUrl' => 'https://www.youtube.com/watch?v=W7rO_mZTuWM',
                'thumbnail' => YouTube::thumbnail('https://www.youtube.com/watch?v=W7rO_mZTuWM'),
                'srcset' => null,
                'duration' => 1800,
                'url' => $appearance->url(),
            ],
        ],
    ]);
});
