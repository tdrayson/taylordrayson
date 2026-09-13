<?php

use App\Actions\BuildResponseContext;
use App\Actions\Citations\FetchCitation;
use App\Actions\Citations\StoreCitation;
use App\Enums\ResponseKind;
use App\Models\Note;
use Illuminate\Support\Facades\Http;

const POST = 'https://example.com/post';

function hEntry(string $author): string
{
    return <<<HTML
    <html><head><title>Ignored page title</title></head><body>
    <article class="h-entry">
        <h1 class="p-name">Sending your First Webmention from Scratch</h1>
        {$author}
        <time class="dt-published" datetime="2018-06-30T20:35:00-07:00">30 June</time>
        <div class="e-content"><p>Webmention is one of the fundamental building blocks.</p></div>
    </article>
    </body></html>
    HTML;
}

it('reads everything from one request when the page names its author', function () {
    Http::fake([POST => Http::response(hEntry(
        '<a class="p-author h-card" href="https://example.com/"><img class="u-photo" src="https://example.com/me.jpg" alt="">Aaron Parecki</a>'
    ))]);

    $citation = app(FetchCitation::class)(POST);

    expect($citation->title)->toBe('Sending your First Webmention from Scratch')
        ->and($citation->authorName)->toBe('Aaron Parecki')
        ->and($citation->authorPhotoUrl)->toBe('https://example.com/me.jpg')
        ->and($citation->excerpt)->toBe('Webmention is one of the fundamental building blocks.')
        ->and($citation->publishedTimezone)->toBe('-07:00')
        ->and($citation->site)->toBe('example.com');

    Http::assertSentCount(1);
});

// aaronparecki.com links its author to the homepage, where the h-card lives.
it('follows an author named only by a link to find their name and photo', function () {
    Http::fake([
        POST => Http::response(hEntry('<a class="u-author" href="https://example.com/">https://example.com/</a>')),
        'https://example.com/' => Http::response(
            '<div class="h-card"><a class="u-url p-name" href="https://example.com/">Aaron Parecki</a><img class="u-photo" src="https://example.com/me.jpg" alt=""></div>'
        ),
    ]);

    $citation = app(FetchCitation::class)(POST);

    expect($citation->authorName)->toBe('Aaron Parecki')
        ->and($citation->authorPhotoUrl)->toBe('https://example.com/me.jpg');

    Http::assertSentCount(2);
});

// A post page carries other people's h-cards too; only one for the author counts.
it('never names the author after an unrelated h-card on the same page', function () {
    Http::fake([
        POST => Http::response(hEntry('<a class="u-author" href="https://example.com/">https://example.com/</a>')
            .'<div class="h-card"><a class="u-url p-name" href="https://okta.example.com/">Okta</a></div>'),
        'https://example.com/' => Http::response('<p>No h-card here.</p>'),
    ]);

    expect(app(FetchCitation::class)(POST)->authorName)->toBeNull();
});

// A response shown under the post can carry the author's own h-card, but with
// the wrong (lazy-loaded placeholder) photo: it must not stand in for the page's own author card.
it('fetches the author homepage instead of an h-card nested in a response under the post', function () {
    Http::fake([
        POST => Http::response(
            '<article class="h-entry">'
            .'<a class="u-author" href="https://example.com/">https://example.com/</a>'
            .'<div class="p-comment h-cite">'
            .'<span class="p-author h-card">'
            .'<a class="u-url p-name" href="https://example.com/">Example Author</a>'
            .'<img class="u-photo" src="https://example.com/placeholder.png" alt="">'
            .'</span>'
            .'</div>'
            .'</article>'
        ),
        'https://example.com/' => Http::response(
            '<div class="h-card"><a class="u-url p-name" href="https://example.com/">Example Author</a><img class="u-photo" src="https://example.com/real.jpg" alt=""></div>'
        ),
    ]);

    $citation = app(FetchCitation::class)(POST);

    expect($citation->authorPhotoUrl)->toBe('https://example.com/real.jpg');

    Http::assertSentCount(2);
});

it('falls back to OpenGraph for a page with no microformats', function () {
    Http::fake([POST => Http::response(<<<'HTML'
        <html><head>
            <title>Page title</title>
            <meta property="og:title" content="An OpenGraph title">
            <meta property="og:description" content="What the page says about itself.">
            <meta property="article:author" content="Jo Bloggs">
            <meta property="article:published_time" content="2025-05-08T10:27:00+01:00">
        </head><body><p>Hello.</p></body></html>
        HTML)]);

    $citation = app(FetchCitation::class)(POST);

    expect($citation->title)->toBe('An OpenGraph title')
        ->and($citation->excerpt)->toBe('What the page says about itself.')
        ->and($citation->authorName)->toBe('Jo Bloggs')
        ->and($citation->publishedAt->format('H:i'))->toBe('09:27')
        ->and($citation->publishedTimezone)->toBe('+01:00');
});

it('takes the page title when nothing else names the post', function () {
    Http::fake([POST => Http::response('<html><head><title>Sitemap - Kev Quirk</title></head><body></body></html>')]);

    $citation = app(FetchCitation::class)(POST);

    expect($citation->title)->toBe('Sitemap - Kev Quirk')
        ->and($citation->authorName)->toBeNull()
        ->and($citation->excerpt)->toBeNull();
});

it('returns nothing for a page it could not read', function () {
    Http::fake([POST => Http::response('', 500)]);

    expect(app(FetchCitation::class)(POST))->toBeNull();
});

it('caps the excerpt at 600 characters', function () {
    Http::fake([POST => Http::response(
        '<article class="h-entry"><div class="e-content"><p>'.str_repeat('word ', 400).'</p></div></article>'
    )]);

    expect(mb_strlen(app(FetchCitation::class)(POST)->excerpt))->toBeLessThanOrEqual(600);
});

it('renders the published time at the author\'s own clock', function () {
    Http::fake([POST => Http::response(hEntry('<span class="p-author h-card">Aaron Parecki</span>'))]);

    $citation = app(StoreCitation::class)(app(FetchCitation::class)(POST));
    $note = Note::factory()->create(['response_kind' => ResponseKind::Reply, 'response_url' => POST]);

    $published = app(BuildResponseContext::class)($note->fresh())->toArray()['cited']['published'];

    expect($citation->fresh()->published_timezone)->toBe('-07:00')
        ->and($published['label'])->toContain('8:35pm')
        ->and($published['offset'])->toBe('-07:00');
});
