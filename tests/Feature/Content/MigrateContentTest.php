<?php

use App\Models\Article;
use App\Models\Note;
use App\Models\Page;
use Statamic\Facades\Entry;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

it('migrates an eloquent article into a statamic entry', function () {
    $a = Article::factory()->create([
        'slug' => 'first-post', 'title' => 'First', 'occurred_at' => '2024-01-02 09:00:00',
        'content' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Hi']]]],
    ]);
    $this->artisan('content:migrate')->assertSuccessful();
    $entry = Entry::query()->where('collection', 'articles')->where('slug', 'first-post')->first();
    expect($entry)->not->toBeNull();
    expect($entry->date()->toDateString())->toBe('2024-01-02');
});

it('migrates a note with the slug derived from its id', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-15 10:00:00',
        'content' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Quick note']]]],
    ]);
    $this->artisan('content:migrate')->assertSuccessful();
    $slug = "note-{$note->id}";
    $entry = Entry::query()->where('collection', 'notes')->where('slug', $slug)->first();
    expect($entry)->not->toBeNull();
    expect($entry->date()->toDateString())->toBe('2024-03-15');
});

it('migrates a page without a date', function () {
    Page::factory()->create([
        'slug' => 'about', 'title' => 'About', 'draft' => false,
        'content' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'About us']]]],
    ]);
    $this->artisan('content:migrate')->assertSuccessful();
    $entry = Entry::query()->where('collection', 'pages')->where('slug', 'about')->first();
    expect($entry)->not->toBeNull();
    expect($entry->published())->toBeTrue();
});

it('is idempotent and does not duplicate entries on repeated runs', function () {
    Article::factory()->create([
        'slug' => 'dup-post', 'title' => 'Dup', 'occurred_at' => '2024-06-01 00:00:00',
        'content' => ['blocks' => []],
    ]);
    $this->artisan('content:migrate')->assertSuccessful();
    $this->artisan('content:migrate')->assertSuccessful();
    $count = Entry::query()->where('collection', 'articles')->where('slug', 'dup-post')->count();
    expect($count)->toBe(1);
});

it('marks a draft article as unpublished', function () {
    Article::factory()->create([
        'slug' => 'draft-article', 'title' => 'Draft', 'occurred_at' => '2024-01-01 00:00:00',
        'draft' => true, 'content' => ['blocks' => []],
    ]);
    $this->artisan('content:migrate')->assertSuccessful();
    $entry = Entry::query()->where('collection', 'articles')->where('slug', 'draft-article')->first();
    expect($entry)->not->toBeNull();
    expect($entry->published())->toBeFalse();
});
