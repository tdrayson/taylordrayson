<?php

use App\Enums\EntryStatus;
use App\Enums\ResponseKind;
use App\Enums\RsvpValue;
use App\Models\Article;
use App\Models\Citation;
use App\Models\Note;
use App\Models\User;
use App\Support\PortableText;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Queue::fake();
});

/** A response note, created the way the editor creates one. */
function respond(array $payload): Note
{
    test()->postJson('/entries/note', $payload)->assertRedirect();

    return Note::query()->latest('id')->firstOrFail();
}

it('names a like after the domain it likes', function () {
    $note = respond(['response_kind' => 'like', 'response_url' => 'https://www.example.com/post']);

    expect($note->getAttributes()['slug'])->toBe('like-example-com');
});

it('names a repost after the domain', function () {
    $note = respond(['response_kind' => 'repost', 'response_url' => 'https://aaronparecki.com/a/post']);

    expect($note->getAttributes()['slug'])->toBe('repost-aaronparecki-com');
});

it('names a reply after the title of the stored post', function () {
    Citation::factory()->create(['url' => 'https://example.com/post', 'title' => 'Sending your First Webmention', 'author_name' => 'Aaron Parecki']);

    $note = respond(['content' => 'Agreed.', 'response_kind' => 'reply', 'response_url' => 'https://example.com/post']);

    expect($note->getAttributes()['slug'])->toBe('reply-to-sending-your-first-webmention');
});

it('cuts a long title to the words a note slug uses', function () {
    Citation::factory()->create(['url' => 'https://example.com/post', 'title' => 'One two three four five six seven eight']);

    $note = respond(['content' => 'Agreed.', 'response_kind' => 'reply', 'response_url' => 'https://example.com/post']);

    expect($note->getAttributes()['slug'])->toBe('reply-to-one-two-three-four-five-six');
});

it('names a reply after the author when the post has no title', function () {
    Citation::factory()->create(['url' => 'https://example.com/post', 'title' => null, 'author_name' => 'Aaron Parecki']);

    $note = respond(['content' => 'Agreed.', 'response_kind' => 'reply', 'response_url' => 'https://example.com/post']);

    expect($note->getAttributes()['slug'])->toBe('reply-to-aaron-parecki');
});

it('names a reply after the domain when nothing is stored', function () {
    $note = respond(['content' => 'Agreed.', 'response_kind' => 'reply', 'response_url' => 'https://example.com/post']);

    expect($note->getAttributes()['slug'])->toBe('reply-to-example-com');
});

it('names an rsvp after the event title', function () {
    Citation::factory()->create(['url' => 'https://example.com/event', 'title' => 'IndieWebCamp Brighton', 'author_name' => 'Somebody']);

    $note = respond(['response_kind' => 'rsvp', 'rsvp_value' => RsvpValue::Yes->value, 'response_url' => 'https://example.com/event']);

    expect($note->getAttributes()['slug'])->toBe('rsvp-indiewebcamp-brighton');
});

it('names a response to one of my own entries after that entry', function () {
    $article = Article::factory()->create(['title' => 'My Great Article', 'status' => EntryStatus::Published]);

    $note = respond(['response_kind' => 'like', 'response_url' => rtrim(config('app.url'), '/').$article->url()]);

    expect($note->getAttributes()['slug'])->toBe('like-my-great-article');
});

it('keeps a hand-written slug as typed', function () {
    $note = respond(['response_kind' => 'like', 'response_url' => 'https://example.com/post', 'slug' => 'nice-one']);

    expect($note->getAttributes()['slug'])->toBe('nice-one');
});

it('leaves a plain note deriving its slug from its words', function () {
    $note = respond(['content' => 'Just a thought.']);

    expect($note->getAttributes()['slug'])->toBeNull()
        ->and($note->slug())->toBe('just-a-thought');
});

it('keeps the stored slug when the post is fetched later', function () {
    $note = respond(['content' => 'Agreed.', 'response_kind' => 'reply', 'response_url' => 'https://example.com/post']);
    $url = $note->url();

    $citation = Citation::factory()->create(['url' => 'https://example.com/post', 'title' => 'A Title Arriving Late']);
    $note->update(['citation_id' => $citation->id]);

    expect($note->fresh()->getAttributes()['slug'])->toBe('reply-to-example-com')
        ->and($note->fresh()->url())->toBe($url);
});

it('suffixes two same-day likes of one domain', function () {
    $first = respond(['response_kind' => 'like', 'response_url' => 'https://example.com/one', 'occurred_at' => '2026-09-13 09:00:00']);
    $second = respond(['response_kind' => 'like', 'response_url' => 'https://example.com/two', 'occurred_at' => '2026-09-13 10:00:00']);

    expect($first->url())->toBe('/2026/09/13/like-example-com')
        ->and($second->url())->toBe('/2026/09/13/like-example-com-2');
});

it('stores the slug however the note is created', function () {
    $note = Note::factory()->create([
        'content' => PortableText::fromPlainText(''),
        'response_kind' => ResponseKind::Like,
        'response_url' => 'https://example.com/post',
    ]);

    expect($note->getAttributes()['slug'])->toBe('like-example-com');
});
