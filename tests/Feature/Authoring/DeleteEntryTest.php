<?php

use App\Models\Article;
use App\Models\Note;
use App\Models\TimelineEntry;
use App\Models\User;

it('keeps delete behind the login', function () {
    $note = Note::factory()->create();

    $this->delete("/entries/note/{$note->id}")->assertRedirect('/login');

    expect(Note::query()->find($note->id))->not->toBeNull();
});

it('deletes a published note and its timeline entry, then redirects to the notes index', function () {
    $this->actingAs(User::factory()->create());

    $note = Note::factory()->create(['status' => 'published']);

    $this->delete("/entries/note/{$note->id}")->assertRedirect('/notes');

    expect(Note::query()->find($note->id))->toBeNull()
        ->and(TimelineEntry::withoutGlobalScopes()->where('dataset', 'note')->where('entry_id', $note->id)->exists())->toBeFalse();
});

it('redirects to /drafts when the deleted entry was a draft', function () {
    $this->actingAs(User::factory()->create());

    $article = Article::factory()->draft()->create();

    $this->delete("/entries/article/{$article->id}")->assertRedirect('/drafts');

    expect(Article::query()->find($article->id))->toBeNull();
});

it('404s an unknown type', function () {
    $this->actingAs(User::factory()->create());

    $this->delete('/entries/sleep/1')->assertNotFound();
});
