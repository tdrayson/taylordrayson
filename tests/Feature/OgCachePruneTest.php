<?php

use App\Models\Note;
use App\Models\TimelineEntry;
use App\Support\OgRenderer;
use Illuminate\Support\Facades\Storage;

it('keeps the current generation and deletes superseded ones', function () {
    Storage::fake('local');

    $current = OgRenderer::generation();

    Storage::disk('local')->put("og/{$current}/page/abc-def.png", 'current');
    Storage::disk('local')->put("og/{$current}/preview/live.png", 'current');
    Storage::disk('local')->put('og/0123456789ab/dead.png', 'superseded');
    Storage::disk('local')->put('og/ba9876543210/entry/dead.png', 'superseded');
    // The pre-generation layout wrote cards straight into og/.
    Storage::disk('local')->put('og/deadbeef.png', 'old layout');

    $this->artisan('og:clear --stale')->assertSuccessful();

    Storage::disk('local')->assertExists("og/{$current}/page/abc-def.png");
    Storage::disk('local')->assertExists("og/{$current}/preview/live.png");
    Storage::disk('local')->assertMissing('og/0123456789ab/dead.png');
    Storage::disk('local')->assertMissing('og/ba9876543210/entry/dead.png');
    Storage::disk('local')->assertMissing('og/deadbeef.png');
});

it('deletes current cards nothing can reach: old-layout ones and deleted entries\'', function () {
    Storage::fake('local');

    $current = OgRenderer::generation();
    $entry = TimelineEntry::query()->where('entry_id', Note::factory()->create()->id)->sole();

    Storage::disk('local')->put("og/{$current}/entry/{$entry->id}-1.png", 'live');
    Storage::disk('local')->put("og/{$current}/entry/999999-1.png", 'deleted entry');
    Storage::disk('local')->put("og/{$current}/entry/".md5('old').'.png', 'old layout');
    Storage::disk('local')->put("og/{$current}/".md5('old').'.png', 'old layout');

    $this->artisan('og:clear --stale')->assertSuccessful();

    Storage::disk('local')->assertExists("og/{$current}/entry/{$entry->id}-1.png");
    Storage::disk('local')->assertMissing("og/{$current}/entry/999999-1.png");
    Storage::disk('local')->assertMissing("og/{$current}/entry/".md5('old').'.png');
    Storage::disk('local')->assertMissing("og/{$current}/".md5('old').'.png');
});

it('says so when there is nothing stale to delete', function () {
    Storage::fake('local');

    Storage::disk('local')->put('og/'.OgRenderer::generation().'/page/abc-def.png', 'current');

    $this->artisan('og:clear --stale')
        ->expectsOutputToContain('only generation')
        ->assertSuccessful();
});

it('clears every generation without --stale', function () {
    Storage::fake('local');

    $current = OgRenderer::generation();

    Storage::disk('local')->put("og/{$current}/live.png", 'current');
    Storage::disk('local')->put('og/0123456789ab/dead.png', 'superseded');

    $this->artisan('og:clear')->assertSuccessful();

    Storage::disk('local')->assertMissing("og/{$current}/live.png");
    Storage::disk('local')->assertMissing('og/0123456789ab/dead.png');
});

it('moves the generation when the card template changes', function () {
    $before = OgRenderer::generation();

    expect($before)->toHaveLength(12)
        ->and($before)->toMatch('/^[0-9a-f]+$/');

    // The digest is of the template file, so a different OG_VERSION alone must
    // also move it: that is what makes a manual sweep possible.
    config(['og.version' => 'a-different-version']);

    // Memoised per process, so the change is asserted through a fresh hash
    // rather than a second call.
    $after = substr(md5('a-different-version|'.md5_file(resource_path('views/og/card.blade.php'))), 0, 12);

    expect($after)->not->toBe($before);
});
