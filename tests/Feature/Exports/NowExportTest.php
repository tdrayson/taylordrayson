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

it('publishes last night, reading, the latest episode and recent photos as fields', function () {
    Sleep::factory()->create(['occurred_at' => today(), 'duration' => 27300, 'status' => 'published']);
    ThisWeekWith::factory()->create([
        'occurred_at' => now()->subDay(), 'season_number' => 3, 'episode_number' => 12,
        'topic' => 'Side Projects', 'status' => 'published',
    ]);

    $export = (new NowExport)->present();

    expect($export->type)->toBe('now')
        ->and($export->field('sleep')->display)->toBe('7h 35m')
        ->and($export->field('sleep')->raw)->toBe(27300)
        ->and($export->field('episode')->display)->toBe('S3E12, Side Projects')
        ->and($export->field('episode')->raw)->toBe(['season' => 3, 'episode' => 12]);

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('sleep', 'episode');
});

it('offers neither geojson nor ics for now, since it has no aspects', function () {
    $formats = array_keys(Formats::for((new NowExport)->present()));

    expect($formats)->not->toContain('geojson')
        ->not->toContain('ics');
});
