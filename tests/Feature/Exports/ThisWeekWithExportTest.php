<?php

use App\Models\ThisWeekWith;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes a this week with episode as labelled fields in order', function () {
    $episode = ThisWeekWith::factory()->create([
        'occurred_at' => '2026-09-13 09:00:00',
        'season_number' => 2,
        'episode_number' => 5,
        'topic' => 'Side projects',
        'duration' => 3600,
        'show_notes' => 'What we talked about this week.',
        'audio_url' => 'https://example.com/tww-2-5.mp3',
        'video_url' => 'https://www.youtube.com/watch?v=abc123',
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($episode);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['episode', 'topic', 'duration'])
        ->and($export->field('episode')->display)->toBe('S2E5')
        ->and($export->field('episode')->raw)->toBe(['season' => 2, 'episode' => 5])
        ->and($export->field('topic')->display)->toBe('Side projects')
        ->and($export->field('duration')->display)->toBe('1h');

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('listen', 'watch', 'type', 'day')
        ->and($export->body)->toBe('What we talked about this week.');
});

it('offers ics for a this week with episode, via its duration span', function () {
    $episode = ThisWeekWith::factory()->create([
        'occurred_at' => '2026-09-13 09:00:00', 'duration' => 3600, 'status' => 'published',
    ]);

    $formats = array_keys(Formats::for(ExportPresenter::for($episode)));

    expect($formats)->toContain('ics')
        ->not->toContain('geojson');
});

it('never leaks an id, a timestamp or a password', function () {
    $episode = ThisWeekWith::factory()->create(['occurred_at' => '2026-09-13 09:00:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($episode)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
