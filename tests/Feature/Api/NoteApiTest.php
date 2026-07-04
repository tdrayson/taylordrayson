<?php

use App\Models\Note;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

it('creates a note', function () {
    $this->withToken('test-token')->postJson('/api/v1/notes', [
        'content' => 'Espresso was dialled in perfectly today.',
        'occurred_at' => '2026-07-04 09:15:00',
    ])
        ->assertCreated()
        ->assertJsonPath('data.content', 'Espresso was dialled in perfectly today.');

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
        ->assertJsonPath('data.content', 'Before');

    $this->withToken('test-token')->patchJson("/api/v1/notes/{$note->id}", ['content' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.content', 'After');

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
