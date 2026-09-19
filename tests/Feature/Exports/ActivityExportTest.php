<?php

use App\Models\Activity;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes an activity as labelled fields in order', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-09-13 13:16:01',
        'type' => 'walk',
        'name' => 'Afternoon Walk',
        'duration' => 1492,
        'distance' => 1677,
        'calories' => 107,
        'average_heart_rate' => 98.6,
        'max_heart_rate' => 142,
        'timezone' => 'Europe/London',
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($activity);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['activity', 'name', 'distance', 'duration', 'calories', 'average_heart_rate', 'max_heart_rate'])
        ->and($export->field('activity')->display)->toBe('Walk')
        ->and($export->field('distance')->display)->toBe('1.0 miles')
        ->and($export->field('distance')->raw)->toBe(1677)
        ->and($export->field('duration')->display)->toBe('24m')
        ->and($export->field('calories')->display)->toBe('107 kcal')
        ->and($export->field('average_heart_rate')->display)->toBe('99 bpm')
        ->and($export->field('max_heart_rate')->display)->toBe('142 bpm');
});

it('offers ics for an activity but geojson only with a track', function () {
    $withTrack = Activity::factory()->create([
        'occurred_at' => '2026-09-13 13:16:01', 'duration' => 1492, 'status' => 'published',
        'track' => [
            ['time' => 0, 'lat' => 51.5, 'lng' => -0.1],
            ['time' => 60, 'lat' => 51.51, 'lng' => -0.11],
        ],
    ]);
    $withoutTrack = Activity::factory()->create([
        'occurred_at' => '2026-09-13 13:16:01', 'duration' => 1492, 'track' => null, 'status' => 'published',
    ]);

    $withTrackFormats = array_keys(Formats::for(ExportPresenter::for($withTrack)));
    $withoutTrackFormats = array_keys(Formats::for(ExportPresenter::for($withoutTrack)));

    expect($withTrackFormats)->toContain('ics', 'geojson')
        ->and($withoutTrackFormats)->toContain('ics')->not->toContain('geojson');
});

it('never leaks an id, a timestamp or a password', function () {
    $activity = Activity::factory()->create(['occurred_at' => '2026-09-13 13:16:01', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($activity)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
