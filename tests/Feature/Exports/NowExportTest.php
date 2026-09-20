<?php

use App\Models\Page;
use App\Models\Sleep;
use App\Models\ThisWeekWith;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\NowExport;

it('serves now as json', function () {
    $this->get('/now.json')
        ->assertOk()
        ->assertJsonPath('type', 'now');
});

it('reaches the now export rather than the page export', function () {
    Page::factory()->create(['slug' => 'now', 'title' => 'A page called now', 'status' => 'published']);

    expect($this->get('/now.json')->json('title'))->not->toBe('A page called now');
});

it('publishes last night and the latest episode as fields', function () {
    Sleep::factory()->create(['occurred_at' => today(), 'duration' => 27300, 'status' => 'published']);
    ThisWeekWith::factory()->create([
        'occurred_at' => now()->subDay(), 'season_number' => 3, 'episode_number' => 12,
        'topic' => 'Side Projects', 'status' => 'published',
    ]);

    $export = (new NowExport)->present();

    // The episode's topic is deliberately absent: a rundown runs to hundreds
    // of characters and told a reader nothing the number does not.
    expect($export->type)->toBe('now')
        ->and($export->field('slept')->display)->toBe('7h 35m')
        ->and($export->field('slept')->raw)->toBe(27300)
        ->and($export->field('season')->display)->toBe('3')
        ->and($export->field('episode')->display)->toBe('12')
        ->and($export->field('topic'))->toBeNull();

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('sleep', 'episode');
});

it('offers neither geojson nor ics for now, since it has no aspects', function () {
    $formats = array_keys(Formats::for((new NowExport)->present()));

    expect($formats)->not->toContain('geojson')
        ->not->toContain('ics');
});
