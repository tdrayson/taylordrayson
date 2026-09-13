<?php

use App\Models\Note;
use App\Models\User;
use App\Support\PortableText;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Queue::fake();
});

it('posts a like, repost or rsvp with no body', function (array $payload) {
    $this->postJson('/entries/note', ['response_url' => 'https://example.com/post', ...$payload])
        ->assertRedirect();

    expect(Note::sole()->content)->toBe([]);
})->with([
    'like' => [['response_kind' => 'like']],
    'repost' => [['response_kind' => 'repost']],
    'rsvp' => [['response_kind' => 'rsvp', 'rsvp_value' => 'yes']],
]);

it('refuses a plain note or a reply with no body', function (array $payload) {
    $this->postJson('/entries/note', $payload)->assertJsonValidationErrors('content');

    expect(Note::count())->toBe(0);
})->with([
    'plain' => [[]],
    'reply' => [['response_kind' => 'reply', 'response_url' => 'https://example.com/post']],
]);

it('refuses an update that blanks a plain note', function () {
    $note = Note::factory()->create(['content' => PortableText::fromPlainText('Still here.')]);

    $this->patchJson("/entries/note/{$note->id}", ['content' => []])->assertJsonValidationErrors('content');

    expect(PortableText::plainText($note->fresh()->content))->toBe('Still here.');
});

it('lets an update blank a like', function () {
    $note = Note::factory()->create(['response_kind' => 'like', 'response_url' => 'https://example.com/post']);

    $this->patchJson("/entries/note/{$note->id}", ['content' => [], 'response_kind' => 'like', 'response_url' => 'https://example.com/post'])
        ->assertRedirect();

    expect($note->fresh()->content)->toBe([]);
});
