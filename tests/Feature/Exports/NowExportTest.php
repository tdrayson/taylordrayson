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

it('publishes last night as a field, and not the podcast episode', function () {
    Sleep::factory()->create(['occurred_at' => today(), 'duration' => 27300, 'status' => 'published']);
    ThisWeekWith::factory()->create([
        'occurred_at' => now()->subDay(), 'season_number' => 3, 'episode_number' => 12,
        'topic' => 'Side Projects', 'status' => 'published',
    ]);

    $export = (new NowExport)->present();

    // This Week With is published here, not listened to, so /now carries no
    // episode at all.
    expect($export->type)->toBe('now')
        ->and($export->field('slept')->display)->toBe('7h 35m')
        ->and($export->field('slept')->raw)->toBe(27300)
        ->and($export->field('episode'))->toBeNull()
        ->and($export->field('season'))->toBeNull();

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('sleep')->and($links)->not->toContain('episode');
});

it('offers neither geojson nor ics for now, since it has no aspects', function () {
    $formats = array_keys(Formats::for((new NowExport)->present()));

    expect($formats)->not->toContain('geojson')
        ->not->toContain('ics');
});
