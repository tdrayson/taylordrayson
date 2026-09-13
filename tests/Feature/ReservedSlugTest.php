<?php

use App\Models\Activity;
use App\Models\Note;
use App\Models\Sleep;
use App\Models\User;
use App\Support\PortableText;

use function Pest\Laravel\get;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('reserves the bare sleep slug so a same-day activity never holds it', function () {
    $activity = Activity::factory()->create(['name' => 'Sleep', 'type' => 'workout', 'occurred_at' => '2026-03-15 07:00:00']);

    expect($activity->fresh()->url())->toBe('/2026/03/15/sleep-2');

    $sleep = Sleep::factory()->create(['occurred_at' => '2026-03-15 07:00:00', 'started_at' => '2026-03-14 23:00:00']);

    expect($activity->fresh()->url())->toBe('/2026/03/15/sleep-2')
        ->and($sleep->fresh()->url())->toBe('/2026/03/15/sleep');

    get($activity->fresh()->url())->assertSuccessful();
    get($sleep->fresh()->url())->assertSuccessful();
});

it('leaves an ordinary note with no clash on its bare slug', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-03-15 09:00:00', 'content' => PortableText::fromPlainText('Just a normal note today')]);

    expect($note->fresh()->url())->toBe('/2026/03/15/just-a-normal-note-today');

    get($note->fresh()->url())->assertSuccessful();
});

it('rejects a typed slug that collides with a reserved word', function () {
    $this->post('/entries/note', ['content' => 'A note.', 'slug' => 'food'])
        ->assertSessionHasErrors('slug');

    $this->post('/entries/article', ['title' => 'An article', 'slug' => 'food'])
        ->assertSessionHasErrors('slug');

    $this->post('/entries/project', ['title' => 'A project', 'stage' => 'active', 'slug' => 'food'])
        ->assertSessionHasErrors('slug');

    expect(Note::count())->toBe(0);
});

it('rejects a note whose content would generate a reserved slug', function () {
    $this->post('/entries/note', ['content' => 'Sleep'])
        ->assertSessionHasErrors('slug');

    expect(Note::count())->toBe(0);
});
