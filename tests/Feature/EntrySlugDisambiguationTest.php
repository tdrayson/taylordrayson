<?php

use App\Models\Activity;
use App\Models\Article;
use App\Models\Note;

use function Pest\Laravel\get;

it('gives two same-named activities on the same day distinct urls that each resolve', function () {
    $first = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 08:00:00']);
    $second = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 17:30:00']);

    expect($first->url())->not->toBe($second->url());

    get($first->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Entry')->where('entry.id', $first->id));

    get($second->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Entry')->where('entry.id', $second->id));
});

it('still resolves a legacy bare slug when it is unique on the day', function () {
    $activity = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 08:00:00']);

    get('/2026/03/15/morning-walk')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Entry')->where('entry.id', $activity->id));
});

it('resolves an ambiguous legacy bare slug deterministically to the earliest entry', function () {
    $later = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 17:30:00']);
    $earlier = Activity::factory()->create(['name' => 'Morning Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 08:00:00']);

    get('/2026/03/15/morning-walk')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Entry')->where('entry.id', $earlier->id));
});

it('keeps the note-{id} url shape for notes', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-03-15 09:00:00']);

    expect($note->url())->toBe("/2026/03/15/note-{$note->id}");

    get($note->url())->assertSuccessful();
});

it('keeps bare stored slugs for article urls', function () {
    $article = Article::factory()->create(['slug' => 'my-great-post', 'published' => true, 'occurred_at' => '2026-03-15 09:00:00']);

    expect($article->url())->toBe('/2026/03/15/my-great-post');

    get($article->url())->assertSuccessful();
});
