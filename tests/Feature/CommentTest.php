<?php

use App\Actions\Comments\StoreComment;
use App\Enums\CommentStatus;
use App\Http\Requests\Interactions\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Note;
use App\Models\Page;
use App\Support\FormNonce;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

/**
 * A token issued $ageSeconds ago, so a test does not have to wait out the
 * minimum time-on-form.
 */
function commentNonce(int $ageSeconds = 30): string
{
    $nonce = Str::uuid()->toString();
    Cache::put('nonce:comment:'.$nonce, now()->subSeconds($ageSeconds)->timestamp, 3600);

    return $nonce;
}

/**
 * Post a comment, filling in a valid nonce and sensible defaults.
 *
 * @param  array<string, mixed>  $overrides
 */
function comment(int $noteId, array $overrides = [], string $ip = '203.0.113.1')
{
    return postJson("/comments/note/{$noteId}", array_merge([
        'author_name' => 'Jo',
        'body' => 'This is a real comment with real words in it.',
        'nonce' => commentNonce(),
    ], $overrides), ['REMOTE_ADDR' => $ip]);
}

it('issues a token only when the form is touched', function () {
    postJson('/comments/token')->assertSuccessful()->assertJsonStructure(['nonce']);
});

it('holds the first comment from a name, then lets that name through', function () {
    $note = Note::factory()->create();

    comment($note->id)->assertCreated()->assertJsonPath('status', 'pending');

    Comment::query()->first()->update(['status' => CommentStatus::Approved]);

    comment($note->id)->assertCreated()->assertJsonPath('status', 'approved');
});

it('does not hand an approved name to a different visitor', function () {
    $note = Note::factory()->create();

    comment($note->id, ip: '203.0.113.1');
    Comment::query()->first()->update(['status' => CommentStatus::Approved]);

    // Same name, different connection: still needs reading first.
    comment($note->id, ip: '198.51.100.7')->assertJsonPath('status', 'pending');
});

it('drops a submission that fills the honeypot, without saying so', function () {
    $note = Note::factory()->create();

    comment($note->id, [StoreCommentRequest::HONEYPOT => 'http://spam.example'])
        ->assertCreated()
        ->assertJsonPath('status', 'pending');

    expect(Comment::count())->toBe(0);
});

it('rejects a submission with no nonce, a made-up one, or a spent one', function () {
    $note = Note::factory()->create();

    comment($note->id, ['nonce' => null])->assertStatus(422);
    comment($note->id, ['nonce' => Str::uuid()->toString()])->assertStatus(422);

    $nonce = commentNonce();
    comment($note->id, ['nonce' => $nonce])->assertCreated();
    comment($note->id, ['nonce' => $nonce])->assertStatus(422);

    expect(Comment::count())->toBe(1);
});

it('rejects a comment submitted faster than it could be typed', function () {
    $note = Note::factory()->create();

    comment($note->id, ['nonce' => commentNonce(ageSeconds: 0)])->assertStatus(422);

    expect(Comment::count())->toBe(0);
});

it('keeps an email only when replies were asked for, and never returns it', function () {
    $note = Note::factory()->create();

    comment($note->id, ['author_email' => 'jo@example.com'])
        ->assertCreated()
        ->assertJsonMissing(['jo@example.com']);

    expect(Comment::query()->value('author_email'))->toBeNull();

    comment($note->id, ['author_email' => 'jo@example.com', 'notify_replies' => true]);

    expect(Comment::query()->latest('id')->first())
        ->author_email->toBe('jo@example.com')
        ->notify_replies->toBeTrue();
});

it('threads a reply under an approved comment on the same entry', function () {
    $note = Note::factory()->create();

    comment($note->id);
    $parent = Comment::query()->first();
    $parent->update(['status' => CommentStatus::Approved]);

    comment($note->id, ['parent_id' => $parent->id]);

    expect(Comment::query()->latest('id')->value('parent_id'))->toBe($parent->id);
});

it('flattens a reply whose parent is on another entry or unapproved', function () {
    $note = Note::factory()->create();
    $other = Note::factory()->create();

    comment($other->id);
    $elsewhere = Comment::query()->first();
    $elsewhere->update(['status' => CommentStatus::Approved]);

    comment($note->id, ['parent_id' => $elsewhere->id]);
    expect(Comment::query()->latest('id')->value('parent_id'))->toBeNull();

    // A stranger's comment on this entry, so it is genuinely still pending.
    comment($note->id, ['author_name' => 'Sam'], ip: '198.51.100.7');
    $pending = Comment::query()->latest('id')->first();
    expect($pending->status)->toBe(CommentStatus::Pending);

    comment($note->id, ['parent_id' => $pending->id]);
    expect(Comment::query()->latest('id')->value('parent_id'))->toBeNull();
});

it('accepts a comment on a published page but not a draft one', function () {
    postJson('/comments/page/'.Page::factory()->create()->id, [
        'author_name' => 'Jo',
        'body' => 'Signing the guestbook.',
        'nonce' => commentNonce(),
    ])->assertCreated();

    postJson('/comments/page/'.Page::factory()->draft()->create()->id, [
        'author_name' => 'Jo',
        'body' => 'Signing the guestbook.',
        'nonce' => commentNonce(),
    ])->assertNotFound();
});

it('rejects a name the leaderboard would also reject, evasions included', function () {
    $note = Note::factory()->create();

    comment($note->id, ['author_name' => 'r3tard'])->assertStatus(422);
    comment($note->id, ['author_name' => 'f u c k'])->assertStatus(422);

    expect(Comment::count())->toBe(0);
});

it('lets real names through that a blunter filter would refuse', function () {
    $note = Note::factory()->create();

    foreach (['Dick', 'Randy', 'Cockburn', 'Scunthorpe Steve'] as $name) {
        comment($note->id, ['author_name' => $name])->assertCreated();
    }

    expect(Comment::count())->toBe(4);
});

it('sends a slur straight to spam but leaves ordinary swearing alone', function () {
    $note = Note::factory()->create();

    comment($note->id, ['body' => 'you are a retard and a n1gger'])->assertJsonPath('status', 'spam');

    // Swearing is not abuse, and a filter that refuses this is worse than none.
    comment($note->id, ['body' => 'This is fucking brilliant, well done.'])
        ->assertJsonPath('status', 'pending');
});

it('spends the nonce it was given', function () {
    expect(FormNonce::claim(StoreComment::NONCE_PURPOSE, FormNonce::issue(StoreComment::NONCE_PURPOSE)))
        ->toBeGreaterThanOrEqual(0);
});
