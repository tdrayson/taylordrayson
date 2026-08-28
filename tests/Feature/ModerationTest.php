<?php

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Note;
use App\Models\Page;
use App\Models\User;
use App\Notifications\ReplyPosted;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/** A comment on $note, held by default. */
function held(Note $note, array $attributes = []): Comment
{
    return $note->morphMany(Comment::class, 'commentable')->create(array_merge([
        'author_name' => 'Jo',
        'body' => 'Something worth reading first.',
        'status' => CommentStatus::Pending,
        'ip_hash' => 'seed',
    ], $attributes));
}

it('is not reachable without signing in', function () {
    get('/moderation')->assertRedirect('/login');
});

it('cannot be shadowed by a page slugged moderation', function () {
    Page::factory()->create(['slug' => 'moderation', 'title' => 'Moderation']);

    // The route is registered above the /{slug} catch-all, so the page loses.
    get('/moderation')->assertRedirect('/login');

    actingAs(User::factory()->create());
    get('/moderation')->assertOk()->assertInertia(fn ($page) => $page->component('Moderation'));
});

it('lists what is waiting and approves it', function () {
    actingAs(User::factory()->create());
    $comment = held(Note::factory()->create());

    get('/moderation')->assertInertia(fn ($page) => $page->has('pending.comments', 1));

    post("/moderation/comment/{$comment->id}/approve")->assertRedirect();

    expect($comment->fresh()->status)->toBe(CommentStatus::Approved);
});

it('keeps spam rather than deleting it, so a miss can be released', function () {
    actingAs(User::factory()->create());
    $comment = held(Note::factory()->create(), ['status' => CommentStatus::Spam]);

    get('/moderation')->assertInertia(fn ($page) => $page
        ->has('spam.comments', 1)
        ->has('pending.comments', 0));

    post("/moderation/comment/{$comment->id}/approve")->assertRedirect();

    expect($comment->fresh()->status)->toBe(CommentStatus::Approved);
});

it('emails a reply only once, and only when it is approved', function () {
    Notification::fake();
    actingAs(User::factory()->create());

    $note = Note::factory()->create();
    $parent = held($note, [
        'author_email' => 'jo@example.com',
        'notify_replies' => true,
        'status' => CommentStatus::Approved,
    ]);
    $reply = held($note, ['author_name' => 'Sam', 'parent_id' => $parent->id]);

    Notification::assertNothingSent();

    post("/moderation/comment/{$reply->id}/approve");
    post("/moderation/comment/{$reply->id}/approve");

    // Approving twice must not mail twice: the second is already approved.
    Notification::assertSentOnDemandTimes(ReplyPosted::class, 1);
});

it('sends nothing to somebody who never asked, or who has unsubscribed', function () {
    Notification::fake();
    actingAs(User::factory()->create());

    $note = Note::factory()->create();

    $silent = held($note, ['author_email' => 'jo@example.com', 'status' => CommentStatus::Approved]);
    post('/moderation/comment/'.held($note, ['parent_id' => $silent->id])->id.'/approve');

    $gone = held($note, [
        'author_email' => 'sam@example.com',
        'notify_replies' => true,
        'unsubscribed_at' => now(),
        'status' => CommentStatus::Approved,
    ]);
    post('/moderation/comment/'.held($note, ['parent_id' => $gone->id])->id.'/approve');

    Notification::assertNothingSent();
});

it('unsubscribes from a signed link, and refuses an unsigned one', function () {
    $comment = held(Note::factory()->create(), [
        'author_email' => 'jo@example.com',
        'notify_replies' => true,
    ]);

    get("/unsubscribe/{$comment->id}")->assertForbidden();

    get(URL::signedRoute('unsubscribe', ['comment' => $comment->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Unsubscribed'));

    // Stamped, not blanked: a reply already queued still knows who it was
    // for and simply does not send.
    expect($comment->fresh())
        ->unsubscribed_at->not->toBeNull()
        ->and($comment->fresh()->wantsReplyNotifications())->toBeFalse();
});
