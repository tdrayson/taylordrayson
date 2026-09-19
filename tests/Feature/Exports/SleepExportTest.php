<?php

use App\Models\Sleep;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes a sleep session as labelled fields in order', function () {
    $sleep = Sleep::factory()->create([
        'started_at' => '2026-09-12 23:00:00',
        'occurred_at' => '2026-09-13 06:30:00',
        'duration' => 27000,
        'deep' => 5400,
        'core' => 14400,
        'rem' => 6300,
        'awake' => 900,
        'score' => 82,
        'source' => 'apple_watch',
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($sleep);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['duration', 'deep', 'deep_share', 'core', 'core_share', 'rem', 'rem_share', 'awake', 'awake_share', 'score', 'source'])
        ->and($export->field('duration')->display)->toBe('7h 30m')
        ->and($export->field('deep')->display)->toBe('1h 30m')
        ->and($export->field('deep_share')->display)->toBe('20%')
        ->and($export->field('core_share')->display)->toBe('53%')
        ->and($export->field('rem_share')->display)->toBe('23%')
        ->and($export->field('awake_share')->display)->toBe('3%')
        ->and($export->field('score')->display)->toBe('82 out of 100')
        ->and($export->field('source')->display)->toBe('Apple Watch')
        ->and($export->field('source')->raw)->toBe('apple_watch');
});

it('offers ics for a sleep session, spanning bedtime to waking', function () {
    $sleep = Sleep::factory()->create([
        'started_at' => '2026-09-12 23:00:00', 'occurred_at' => '2026-09-13 06:30:00', 'status' => 'published',
    ]);

    $available = array_keys(Formats::for(ExportPresenter::for($sleep)));

    expect($available)->toContain('ics')->not->toContain('geojson');
});

it('never leaks an id, a timestamp or a password', function () {
    $sleep = Sleep::factory()->create(['occurred_at' => '2026-09-13 06:30:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($sleep)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
