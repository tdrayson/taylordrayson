<?php

use App\Enums\CommentStatus;
use App\Models\Article;
use App\Models\Note;
use App\Support\PortableText;

/*
 * Microformats are a machine contract: an IndieWeb reader or webmention
 * receiver parses this markup, and nothing on the page looks wrong when it
 * regresses.
 *
 * These run in a browser and parse the rendered DOM, because the markup lives
 * in Vue templates. That means they prove the markup is right once Vue has
 * run, not that it reaches the server-rendered HTML a parser would fetch — see
 * the SSR note in phpunit.xml.
 */

/**
 * The page's rendered DOM, parsed as microformats.
 *
 * Waits on the root element first: script() reads the DOM immediately, so
 * capturing without waiting races Vue and parses an empty shell.
 */
function microformatsOf(string $path, string $root = '.h-entry'): array
{
    $page = visit($path)->assertPresent($root);

    return parseMicroformats(
        $page->script('document.documentElement.outerHTML'),
        config('app.url').$path,
    );
}

it('marks an article permalink up as an h-entry', function () {
    $article = Article::factory()->create([
        'published' => true,
        'title' => 'A titled piece',
        'occurred_at' => '2024-03-01 09:00:00',
        'content' => PortableText::fromPlainText('The body of the piece.'),
    ]);

    $entry = microformatItem(microformatsOf($article->url()), 'h-entry');

    expect($entry['properties']['name'][0])->toBe('A titled piece')
        ->and($entry['properties']['published'][0])->toStartWith('2024-03-01')
        ->and($entry['properties']['url'][0])->toEndWith($article->url())
        ->and($entry['properties']['content'][0]['value'])->toContain('The body of the piece.')
        ->and($entry['properties']['author'][0]['properties']['name'][0])->toBe('Taylor Drayson');
});

// The distinction readers use to tell the two apart: a note is content with no
// name of its own, so the outline-only heading must not carry p-name.
it('leaves a note without a name, so it does not parse as an article', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-02 09:00:00',
        'content' => PortableText::fromPlainText('Just a thought.'),
    ]);

    $entry = microformatItem(microformatsOf($note->url()), 'h-entry');

    expect($entry['properties'])->not->toHaveKey('name')
        ->and($entry['properties']['content'][0]['value'])->toContain('Just a thought.');
});

it('wraps the timeline in an authored h-feed', function () {
    Article::factory()->create(['published' => true, 'occurred_at' => now()->subHour()]);

    $feed = microformatItem(microformatsOf('/', '.h-feed .h-entry'), 'h-feed');

    // The feed carries the author; consumers inherit it for each entry through
    // the authorship-discovery algorithm, so repeating it per entry is noise.
    expect($feed['properties']['author'][0]['properties']['name'][0])->toBe('Taylor Drayson')
        ->and($feed['children'])->not->toBeEmpty()
        ->and($feed['children'][0]['type'])->toContain('h-entry');
});

it('says a comment is a comment on the entry, not a citation beside it', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-03 09:00:00',
        'content' => PortableText::fromPlainText('Something worth answering.'),
    ]);

    $note->comments()->create([
        'author_name' => 'Marty McFly',
        'body' => PortableText::fromPlainText('This is the reply.'),
        'status' => CommentStatus::Approved,
    ]);

    // Without p-comment the h-cite parses as an unassigned child, so the page
    // shows a response without ever saying what it is a response to.
    $entry = microformatItem(microformatsOf($note->url()), 'h-entry');
    $comment = $entry['properties']['comment'][0] ?? null;

    expect($comment)->not->toBeNull()
        ->and($comment['type'])->toContain('h-cite')
        ->and($comment['properties']['author'][0]['properties']['name'][0] ?? $comment['properties']['author'][0])->toBe('Marty McFly')
        ->and($comment['properties']['content'][0]['value'])->toContain('This is the reply.');
});

it('publishes a mention title as a name, never as the content of their post', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-04 09:00:00',
        'content' => PortableText::fromPlainText('Something worth linking to.'),
    ]);

    $note->webmentions()->create([
        'source_url' => 'https://jan.example/posts/now',
        'target_url' => config('app.url').$note->url(),
        'kind' => 'mention',
        'title' => 'Now, summer 2026',
        'author_name' => 'Jan',
        'author_url' => 'https://jan.example/',
        'status' => CommentStatus::Approved,
        'verified_at' => now(),
        'published_at' => now(),
    ]);

    // The title names their post. Publishing it inside e-content told a parser
    // the words "Now, summer 2026" were what they wrote.
    $entry = microformatItem(microformatsOf($note->url()), 'h-entry');

    $citation = collect($entry['children'] ?? [])
        ->first(fn (array $item): bool => in_array('h-cite', $item['type'] ?? [], true));

    expect($citation)->not->toBeNull()
        ->and($citation['properties']['name'][0])->toBe('Now, summer 2026')
        ->and($citation['properties']['url'][0])->toBe('https://jan.example/posts/now')
        ->and($citation['properties'])->not->toHaveKey('content');
});

/** A small real JPEG, since the cover goes through an image pipeline. */
function jpegBytes(): string
{
    $image = imagecreatetruecolor(1280, 720);
    imagefilledrectangle($image, 0, 0, 1279, 719, imagecolorallocate($image, 120, 90, 200));
    ob_start();
    imagejpeg($image, null, 80);

    return (string) ob_get_clean();
}

it('names a tag and a cover as properties of the entry', function () {
    $article = Article::factory()->create([
        'published' => true,
        'title' => 'A tagged piece',
        'occurred_at' => '2024-03-07 09:00:00',
        'content' => PortableText::fromPlainText('The body of the piece.'),
    ]);

    $article->syncTagNames(['indieweb', 'microformats']);
    $article->addMediaFromString(jpegBytes())->usingFileName('cover.jpg')->toMediaCollection('cover');

    // Stored title-cased, and the property carries what the page shows.
    $entry = microformatItem(microformatsOf($article->url()), 'h-entry');

    expect($entry['properties']['category'] ?? [])->toContain('Indieweb', 'Microformats')
        // u-featured on an img with alt parses to { value, alt }, not a bare URL.
        ->and($entry['properties']['featured'][0]['value'] ?? null)->toContain('cover');
});

it('gives each kind of response its own property, not an unnamed child', function (string $kind, string $property) {
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-08 09:00:00',
        'content' => PortableText::fromPlainText('Something worth responding to.'),
    ]);

    $note->webmentions()->create([
        'source_url' => 'https://jan.example/posts/now',
        'target_url' => config('app.url').$note->url(),
        'kind' => $kind,
        'author_name' => 'Jan',
        'author_url' => 'https://jan.example/',
        'status' => CommentStatus::Approved,
        'verified_at' => now(),
        'published_at' => now(),
    ]);

    // Without the property the h-cite parses as an unassigned child: the page
    // shows a response without saying what kind of response it is.
    $entry = microformatItem(microformatsOf($note->url()), 'h-entry');

    expect($entry['properties'][$property][0]['type'] ?? [])->toContain('h-cite');
})->with([
    'a like' => ['like', 'like'],
    'a repost' => ['repost', 'repost'],
    'a bookmark' => ['bookmark', 'bookmark'],
]);
