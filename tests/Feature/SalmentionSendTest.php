<?php

use App\Enums\CommentStatus;
use App\Enums\EntryStatus;
use App\Enums\WebmentionKind;
use App\Jobs\SendWebmentions;
use App\Models\Note;
use App\Support\OutboundLinks;
use App\Support\PortableText;
use Illuminate\Support\Facades\Queue;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

/**
 * The sending half of a salmention: a response lands here, so everything this
 * entry links to is told to look at it again.
 */
const UPSTREAM = 'https://example.com/their-post';

beforeEach(function () {
    // Publishing fetches favicons for the hosts an entry links to, which has
    // nothing to do with anything asserted here.
    Saloon::fake(['*' => MockResponse::make('', 404)]);

    config(['webmentions.send' => true]);
});

/** An entry's URL as a sender would address it. */
function upstreamTarget(Note $note): string
{
    return rtrim((string) config('app.url'), '/').$note->url();
}

it('counts the responses under an entry, so a new comment is a change', function () {
    $note = Note::factory()->create();

    $before = OutboundLinks::fingerprint($note);

    $note->comments()->create([
        'author_name' => 'Chris',
        'body' => PortableText::fromPlainText('Nice one.'),
        'status' => CommentStatus::Approved,
    ]);

    expect(OutboundLinks::fingerprint($note->fresh()))->not->toBe($before);
});

it('reads a resave that changed nothing as unchanged', function () {
    $note = Note::factory()->create();

    $before = OutboundLinks::fingerprint($note);
    $note->touch();

    expect(OutboundLinks::fingerprint($note->fresh()))->toBe($before);
});

it('re-announces an entry when a comment on it is approved', function () {
    $note = Note::factory()->create();
    Queue::fake();

    $comment = $note->comments()->create([
        'author_name' => 'Chris',
        'body' => PortableText::fromPlainText('Nice one.'),
        'status' => CommentStatus::Pending,
    ]);

    Queue::assertNothingPushed();

    $comment->update(['status' => CommentStatus::Approved]);

    Queue::assertPushed(SendWebmentions::class, fn (SendWebmentions $job): bool => $job->delay !== null);
});

it('re-announces an entry when a reply arrives by webmention', function () {
    $note = Note::factory()->create();
    Queue::fake();

    $note->webmentions()->create([
        'source_url' => UPSTREAM,
        'target_url' => upstreamTarget($note),
        'kind' => WebmentionKind::Reply->value,
        'status' => CommentStatus::Approved,
    ]);

    Queue::assertPushed(SendWebmentions::class);
});

it('says nothing upstream for a gesture, which puts no words on the page', function (string $kind) {
    $note = Note::factory()->create();
    Queue::fake();

    $note->webmentions()->create([
        'source_url' => UPSTREAM,
        'target_url' => upstreamTarget($note),
        'kind' => $kind,
        'status' => CommentStatus::Approved,
    ]);

    Queue::assertNotPushed(SendWebmentions::class);
})->with(['like', 'repost', 'bookmark', 'reacji']);

it('does not let a response read out of another thread start a send of its own', function () {
    $note = Note::factory()->create();
    Queue::fake();

    $note->webmentions()->create([
        'source_url' => 'https://chris.example.com/1',
        'parent_source_url' => UPSTREAM,
        'target_url' => upstreamTarget($note),
        'kind' => WebmentionKind::Reply->value,
        'status' => CommentStatus::Approved,
    ]);

    Queue::assertNotPushed(SendWebmentions::class);
});

it('collapses a burst of responses into one round of webmentions', function () {
    $note = Note::factory()->create();
    Queue::fake();

    foreach (range(1, 4) as $i) {
        $note->comments()->create([
            'author_name' => "Chris {$i}",
            'body' => PortableText::fromPlainText('Nice one.'),
            'status' => CommentStatus::Approved,
        ]);
    }

    Queue::assertPushed(SendWebmentions::class, 1);
});

it('never announces a draft, whoever responded to it', function () {
    $note = Note::factory()->create(['status' => EntryStatus::Draft]);
    Queue::fake();

    $note->comments()->create([
        'author_name' => 'Chris',
        'body' => PortableText::fromPlainText('Nice one.'),
        'status' => CommentStatus::Approved,
    ]);

    Queue::assertNotPushed(SendWebmentions::class);
});
