<?php

use App\Models\Appearance;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes an appearance as labelled fields in order', function () {
    $appearance = Appearance::factory()->create([
        'occurred_at' => '2026-09-13 15:00:00',
        'type' => 'podcast',
        'title' => 'Building a personal timeline in Laravel',
        'show_name' => 'The Laravel Podcast',
        'duration' => 3600,
        'audio_url' => 'https://example.com/episode.mp3',
        'video_url' => 'https://www.youtube.com/watch?v=vD9nJ_1M3xY',
        'url' => 'https://example.com/show/42',
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($appearance);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['appearance', 'kind', 'show', 'duration'])
        ->and($export->field('appearance')->display)->toBe('Building a personal timeline in Laravel')
        ->and($export->field('kind')->display)->toBe('Podcast')
        ->and($export->field('show')->display)->toBe('The Laravel Podcast')
        ->and($export->field('duration')->display)->toBe('1h');

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('listen', 'watch', 'source', 'type', 'day');
});

it('offers ics for an appearance, and drops a link with no url set', function () {
    $appearance = Appearance::factory()->create([
        'occurred_at' => '2026-09-13 15:00:00', 'duration' => 3600,
        'audio_url' => null, 'video_url' => null, 'url' => null, 'status' => 'published',
    ]);

    $export = ExportPresenter::for($appearance);
    $links = array_map(fn ($l) => $l->key, $export->links);

    expect(array_keys(Formats::for($export)))->toContain('ics')->not->toContain('geojson')
        ->and($links)->not->toContain('listen', 'watch', 'source');
});

it('never leaks an id, a timestamp or a password', function () {
    $appearance = Appearance::factory()->create(['occurred_at' => '2026-09-13 15:00:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($appearance)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
