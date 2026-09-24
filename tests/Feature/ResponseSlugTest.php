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

it('names a like after the title of the stored post', function () {
    Citation::factory()->create(['url' => 'https://www.example.com/post', 'title' => 'Combine Harvester near Aylesby']);

    $note = respond(['response_kind' => 'like', 'response_url' => 'https://www.example.com/post']);

    expect($note->getAttributes()['slug'])->toBe('liked-combine-harvester-near-aylesby');
});

it('names a like after the domain when nothing is stored', function () {
    $note = respond(['response_kind' => 'like', 'response_url' => 'https://www.example.com/post']);

    expect($note->getAttributes()['slug'])->toBe('liked-example-com');
});

it('names a repost after the title, or the domain without one', function () {
    Citation::factory()->create(['url' => 'https://aaronparecki.com/a/post', 'title' => 'A Post Worth Passing On']);

    $titled = respond(['response_kind' => 'repost', 'response_url' => 'https://aaronparecki.com/a/post']);
    $bare = respond(['response_kind' => 'repost', 'response_url' => 'https://aaronparecki.com/a/other']);

    expect($titled->getAttributes()['slug'])->toBe('reposted-a-post-worth-passing-on')
        ->and($bare->getAttributes()['slug'])->toBe('reposted-aaronparecki-com');
});

it('names a reply after the title of the stored post', function () {
    Citation::factory()->create(['url' => 'https://example.com/post', 'title' => 'Sending your First Webmention', 'author_name' => 'Aaron Parecki']);

    $note = respond(['content' => 'Agreed.', 'response_kind' => 'reply', 'response_url' => 'https://example.com/post']);

    expect($note->getAttributes()['slug'])->toBe('replied-to-sending-your-first-webmention');
});

it('cuts a long title to the words a note slug uses', function () {
    Citation::factory()->create(['url' => 'https://example.com/post', 'title' => 'One two three four five six seven eight']);

    $note = respond(['content' => 'Agreed.', 'response_kind' => 'reply', 'response_url' => 'https://example.com/post']);

    expect($note->getAttributes()['slug'])->toBe('replied-to-one-two-three-four-five-six');
});

it('names a reply after the author when the post has no title', function () {
    Citation::factory()->create(['url' => 'https://example.com/post', 'title' => null, 'author_name' => 'Aaron Parecki']);

    $note = respond(['content' => 'Agreed.', 'response_kind' => 'reply', 'response_url' => 'https://example.com/post']);

    expect($note->getAttributes()['slug'])->toBe('replied-to-aaron-parecki');
});

it('names a reply after the domain when nothing is stored', function () {
    $note = respond(['content' => 'Agreed.', 'response_kind' => 'reply', 'response_url' => 'https://example.com/post']);

    expect($note->getAttributes()['slug'])->toBe('replied-to-example-com');
});

it('names an rsvp after the event title', function () {
    Citation::factory()->create(['url' => 'https://example.com/event', 'title' => 'IndieWebCamp Brighton', 'author_name' => 'Somebody']);

    $note = respond(['response_kind' => 'rsvp', 'rsvp_value' => RsvpValue::Yes->value, 'response_url' => 'https://example.com/event']);

    expect($note->getAttributes()['slug'])->toBe('rsvp-to-indiewebcamp-brighton');
});

it('names a response to one of my own entries after that entry\'s slug', function (array $response, string $slug) {
    $article = Article::factory()->create(['title' => 'My Great Article', 'slug' => 'back-under-the-bar', 'status' => EntryStatus::Published]);

    $note = respond([...$response, 'response_url' => rtrim(config('app.url'), '/').$article->url()]);

    expect($note->getAttributes()['slug'])->toBe($slug);
})->with([
    'reply' => [['content' => 'Agreed.', 'response_kind' => 'reply'], 'replied-to-back-under-the-bar'],
    'like' => [['response_kind' => 'like'], 'liked-back-under-the-bar'],
    'repost' => [['response_kind' => 'repost'], 'reposted-back-under-the-bar'],
    'rsvp' => [['response_kind' => 'rsvp', 'rsvp_value' => RsvpValue::Yes->value], 'rsvp-to-back-under-the-bar'],
]);

it('names a response to my own note after the slug its words give it, cut to the word cap', function () {
    $target = Note::factory()->create(['content' => PortableText::fromPlainText('One two three four five six seven eight')]);

    $note = respond(['response_kind' => 'like', 'response_url' => rtrim(config('app.url'), '/').$target->url()]);

    expect($target->url())->toEndWith('/one-two-three-four-five-six')
        ->and($note->getAttributes()['slug'])->toBe('liked-one-two-three-four-five-six');
});

it('cuts a long stored slug of my own entry to the word cap', function () {
    $article = Article::factory()->create(['slug' => 'one-two-three-four-five-six-seven-eight']);

    $note = respond(['response_kind' => 'like', 'response_url' => rtrim(config('app.url'), '/').$article->url()]);

    expect($note->getAttributes()['slug'])->toBe('liked-one-two-three-four-five-six');
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

    expect($note->fresh()->getAttributes()['slug'])->toBe('replied-to-example-com')
        ->and($note->fresh()->url())->toBe($url);
});

it('suffixes two same-day likes of one domain', function () {
    $first = respond(['response_kind' => 'like', 'response_url' => 'https://example.com/one', 'occurred_at' => '2026-09-13 09:00:00']);
    $second = respond(['response_kind' => 'like', 'response_url' => 'https://example.com/two', 'occurred_at' => '2026-09-13 10:00:00']);

    expect($first->url())->toBe('/2026/09/13/liked-example-com')
        ->and($second->url())->toBe('/2026/09/13/liked-example-com-2');
});

it('stores the slug however the note is created', function () {
    $note = Note::factory()->create([
        'content' => PortableText::fromPlainText(''),
        'response_kind' => ResponseKind::Like,
        'response_url' => 'https://example.com/post',
    ]);

    expect($note->getAttributes()['slug'])->toBe('liked-example-com');
});
