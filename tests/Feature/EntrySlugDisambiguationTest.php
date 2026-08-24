<?php

use App\Models\Activity;
use App\Models\Article;
use App\Models\Note;
use App\Support\PortableText;

use function Pest\Laravel\get;

it('gives the first entry the bare slug and the second a -2 suffix', function () {
    $first = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 08:00:00']);
    $second = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 17:30:00']);

    expect($first->url())->toBe('/2026/03/15/morning-walk')
        ->and($second->url())->toBe('/2026/03/15/morning-walk-2');

    get($first->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Entry')->where('entry.id', $first->id));

    get($second->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Entry')->where('entry.id', $second->id));
});

it('keeps suffixes stable when an earlier duplicate is deleted', function () {
    $first = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 08:00:00']);
    $second = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 17:30:00']);

    $first->delete();

    expect($second->fresh()->url())->toBe('/2026/03/15/morning-walk-2');

    get('/2026/03/15/morning-walk-2')->assertSuccessful();
    get('/2026/03/15/morning-walk')->assertNotFound();
});

it('assigns suffixes by insert order so backfilled entries never shift existing urls', function () {
    $evening = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 17:30:00']);
    $backfilled = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 06:00:00']);

    expect($evening->fresh()->url())->toBe('/2026/03/15/morning-walk')
        ->and($backfilled->fresh()->url())->toBe('/2026/03/15/morning-walk-2');
});

it('recomputes the url slug when the name changes', function () {
    $activity = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 08:00:00']);

    $activity->update(['name' => 'Riverside Stroll']);

    expect($activity->fresh()->url())->toBe('/2026/03/15/riverside-stroll');

    get('/2026/03/15/riverside-stroll')->assertSuccessful();
    get('/2026/03/15/morning-walk')->assertNotFound();
});

it('suffixes two notes that open with the same words on one day', function () {
    $content = PortableText::fromPlainText('Same opening words entirely.');
    $first = Note::factory()->create(['occurred_at' => '2026-03-15 09:00:00', 'content' => $content]);
    $second = Note::factory()->create(['occurred_at' => '2026-03-15 11:00:00', 'content' => $content]);

    expect($first->url())->toBe('/2026/03/15/same-opening-words-entirely')
        ->and($second->url())->toBe('/2026/03/15/same-opening-words-entirely-2');

    get($second->url())->assertSuccessful();
});

it('suffixes a same-day same-slug article collision too', function () {
    $first = Article::factory()->create(['slug' => 'launch-day', 'published' => true, 'occurred_at' => '2026-03-15 09:00:00']);
    $second = Article::factory()->create(['slug' => 'launch-day', 'published' => true, 'occurred_at' => '2026-03-15 15:00:00']);

    expect($first->url())->toBe('/2026/03/15/launch-day')
        ->and($second->url())->toBe('/2026/03/15/launch-day-2');

    get($second->url())->assertSuccessful();
});
