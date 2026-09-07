<?php

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Note;
use App\Models\Reaction;
use App\Models\Webmention;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

function spamNonce(): string
{
    $nonce = Str::uuid()->toString();
    Cache::put('nonce:comment:'.$nonce, now()->subSeconds(30)->timestamp, 3600);

    return $nonce;
}

function leave(int $noteId, string $body, string $name = 'Jo', string $ip = '203.0.113.5')
{
    return postJson("/comments/note/{$noteId}", [
        'author_name' => $name,
        'body' => $body,
        'nonce' => spamNonce(),
    ], ['REMOTE_ADDR' => $ip]);
}

/** Approve a comment from this name and IP, so the next one is trusted. */
function trust(Note $note, string $name = 'Jo', string $ip = '203.0.113.5'): void
{
    leave($note->id, 'A perfectly ordinary first comment.', $name, $ip);
    Comment::query()->latest('id')->first()->update(['status' => CommentStatus::Approved]);
}

it('sends a comment stuffed with links straight to spam', function () {
    $note = Note::factory()->create();

    leave($note->id, 'Deals here https://a.example and https://b.example and https://c.example and https://d.example and https://e.example')
        ->assertSuccessful();

    expect(Comment::query()->latest('id')->first()->status)->toBe(CommentStatus::Spam);
});

it('holds a link-heavy comment even from a name already approved', function () {
    $note = Note::factory()->create();
    trust($note);

    // The name is trusted, so without the link rule this would go straight up.
    leave($note->id, 'Look https://a.example and https://b.example and https://c.example')->assertSuccessful();

    expect(Comment::query()->latest('id')->first()->status)->toBe(CommentStatus::Pending);
});

it('still waves through a trusted name citing one or two things', function () {
    $note = Note::factory()->create();
    trust($note);

    leave($note->id, 'This is the piece I meant: https://a.example and also https://b.example')->assertSuccessful();

    expect(Comment::query()->latest('id')->first()->status)->toBe(CommentStatus::Approved);
});

it('counts a link written out and linked as one place, not two', function () {
    $note = Note::factory()->create();
    trust($note);

    // fromPlainText autolinks, so each URL is both an annotation and text.
    leave($note->id, 'Both of these: https://a.example and https://b.example')->assertSuccessful();

    expect(Comment::query()->latest('id')->first()->status)->toBe(CommentStatus::Approved);
});

it('takes the responses with the entry they were left on', function () {
    $note = Note::factory()->create();

    $note->comments()->create(['author_name' => 'Jo', 'body' => [], 'status' => CommentStatus::Approved]);
    $note->reactions()->create(['type' => 'love', 'identity_key' => str_repeat('a', 64)]);
    $note->webmentions()->create(['source_url' => 'https://x.example/p', 'target_url' => 'https://taylordrayson.com/x']);

    $note->delete();

    expect(Comment::query()->count())->toBe(0)
        ->and(Reaction::query()->count())->toBe(0)
        ->and(Webmention::query()->count())->toBe(0);
});

it('explains what is wrong with each field in its own words', function () {
    $note = Note::factory()->create();

    postJson("/comments/note/{$note->id}", [
        'author_name' => 'J',
        'author_email' => 'not-an-address',
        'body' => 'x',
        'nonce' => spamNonce(),
    ], ['REMOTE_ADDR' => '203.0.113.7'])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'author_name' => 'That is too short to be a name.',
            'author_email' => 'That does not look like an email address.',
            'body' => 'That is a little short to post.',
        ]);
});

it('keeps an email that is real and refuses one that is not', function (string $email, bool $valid) {
    $note = Note::factory()->create();

    postJson("/comments/note/{$note->id}", [
        'author_name' => 'Marty McFly',
        'author_email' => $email,
        'body' => 'A comment with an address attached.',
        'nonce' => spamNonce(),
    ], ['REMOTE_ADDR' => '203.0.113.8'])
        ->assertStatus($valid ? 201 : 422);
})->with([
    ['marty@mcfly.com', true],
    ['marty.mcfly+1955@example.co.uk', true],
    ['marty@', false],
    ['@mcfly.com', false],
    ['marty mcfly@example.com', false],
    // RFC-valid, like user@localhost. Rejecting it needs a DNS lookup on the
    // request path, which is a network call and a way for the form to break.
    ['marty@mcfly', true],
]);
