<?php

use App\Models\Note;
use App\Models\Tag;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

it('creates a note', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [
        'content' => 'Espresso was dialled in perfectly today.',
        'occurred_at' => '2026-07-04 09:15:00',
    ])
        ->assertCreated()
        // Notes are Portable Text now: `content` is the block document and
        // `text` carries what `content` used to, so a string client is served.
        ->assertJsonPath('data.text', 'Espresso was dialled in perfectly today.')
        ->assertJsonPath('data.content.0.children.0.text', 'Espresso was dialled in perfectly today.');

    expect(Note::count())->toBe(1)
        ->and(Note::first()->timelineEntry)->not->toBeNull();
});

it('defaults occurred_at to now when omitted', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', ['content' => 'Quick thought.'])
        ->assertCreated();

    expect(Note::first()->occurred_at)->not->toBeNull();
});

it('validates content is required', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);
});

it('rejects unauthenticated writes', function () {
    $this->postJson('/api/v1/notes', ['content' => 'nope'])->assertUnauthorized();

    expect(Note::count())->toBe(0);
});

it('lists notes newest first', function () {
    Note::factory()->create(['occurred_at' => '2026-07-01 10:00:00']);
    Note::factory()->create(['occurred_at' => '2026-07-03 10:00:00']);

    $this->withToken('test-token')->getJson('/api/v1/notes')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.occurred_at', fn ($v) => str_starts_with($v, '2026-07-03'));
});

it('shows, updates, and deletes a note', function () {
    $note = Note::factory()->create(['content' => 'Before']);

    $this->withToken('test-token')->getJson("/api/v1/notes/{$note->id}")
        ->assertOk()
        ->assertJsonPath('data.text', 'Before');

    $this->withToken('test-token')->patchJson("/api/v1/notes/{$note->id}", ['content' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.text', 'After');

    $this->withToken('test-token')->deleteJson("/api/v1/notes/{$note->id}")->assertNoContent();

    expect(Note::count())->toBe(0);
});

it('stores the timezone sent by the client', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [
        'content' => 'Churros for breakfast.',
        'occurred_at' => '2026-07-04 09:15:00',
        'timezone' => 'Europe/Madrid',
    ])
        ->assertCreated()
        ->assertJsonPath('data.timezone', 'Europe/Madrid');

    expect(Note::first()->timezone)->toBe('Europe/Madrid');
});

it('defaults the timezone to the home timezone when omitted', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', ['content' => 'Back home.'])
        ->assertCreated()
        ->assertJsonPath('data.timezone', 'Europe/London');
});

it('rejects an invalid timezone', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [
        'content' => 'Where am I?',
        'timezone' => 'Mars/Olympus_Mons',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['timezone']);
});

it('creates a note with tags and returns them', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [
        'content' => 'Dialled in a new espresso recipe.',
        'tags' => ['Coffee', 'Recipe'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.tags', fn ($tags) => collect($tags)->sort()->values()->all() === ['Coffee', 'Recipe']);

    expect(Note::first()->tagNames())->toEqualCanonicalizing(['Coffee', 'Recipe']);
});

it('reuses the same tag row across notes with the same tag name', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', ['content' => 'First note.', 'tags' => ['Coffee']])
        ->assertCreated();
    $this->withToken('test-token')->postJson('/api/v1/notes', ['content' => 'Second note.', 'tags' => ['Coffee']])
        ->assertCreated();

    expect(Tag::count())->toBe(1);
});

it('updates a note tags via sync', function () {
    $note = Note::factory()->create();
    $note->syncTagNames(['Coffee', 'Recipe']);

    $this->withToken('test-token')->patchJson("/api/v1/notes/{$note->id}", ['tags' => ['Coffee']])
        ->assertOk()
        ->assertJsonPath('data.tags', ['Coffee']);

    expect($note->fresh()->tagNames())->toEqualCanonicalizing(['Coffee']);
});

it('leaves tags untouched when the key is omitted from an update', function () {
    $note = Note::factory()->create();
    $note->syncTagNames(['Coffee']);

    $this->withToken('test-token')->patchJson("/api/v1/notes/{$note->id}", ['content' => 'Updated content.'])
        ->assertOk();

    expect($note->fresh()->tagNames())->toEqualCanonicalizing(['Coffee']);
});

it('rejects an oversized tag name', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [
        'content' => 'Too many characters in this tag.',
        'tags' => [str_repeat('a', 51)],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tags.0']);
});

it('clears tags when patching with empty tags array', function () {
    $note = Note::factory()->create();
    $note->syncTagNames(['Coffee', 'Recipe']);

    expect($note->fresh()->tagNames())->toEqualCanonicalizing(['Coffee', 'Recipe']);

    $this->withToken('test-token')->patchJson("/api/v1/notes/{$note->id}", ['tags' => []])
        ->assertOk()
        ->assertJsonPath('data.tags', []);

    expect($note->fresh()->tagNames())->toBeEmpty();
});

it('uses a custom slug in the note url when provided', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [
        'content' => 'Hot take about coffee grinders.',
        'occurred_at' => '2026-07-04 09:15:00',
        'slug' => 'grinder-hot-take',
    ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'grinder-hot-take')
        ->assertJsonPath('data.url', '/2026/07/04/grinder-hot-take');
});

it('rejects a slug that is not kebab-case', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [
        'content' => 'Bad slug.',
        'slug' => 'Not A Slug!',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

it('reassigns the url when the slug is updated', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-07-04 09:15:00']);

    $this->withToken('test-token')
        ->patchJson("/api/v1/notes/{$note->id}", ['slug' => 'renamed-note'])
        ->assertOk()
        ->assertJsonPath('data.url', '/2026/07/04/renamed-note');

    expect($note->fresh()->url())->toBe('/2026/07/04/renamed-note');
});

it('derives the note url from its opening words when no slug is given', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [
        'content' => 'Plain note.',
        'occurred_at' => '2026-07-04 09:15:00',
    ])
        ->assertCreated()
        ->assertJsonPath('data.url', '/2026/07/04/plain-note');
});

it('falls back to a bare note url when there are no words to use', function () {
    // A note that is only an emoji, or only a photo caption's punctuation, has
    // nothing to slug.
    $this->withToken('test-token')->postJson('/api/v1/notes', [
        'content' => '👍',
        'occurred_at' => '2026-07-05 09:15:00',
    ])
        ->assertCreated()
        ->assertJsonPath('data.url', '/2026/07/05/note');
});
