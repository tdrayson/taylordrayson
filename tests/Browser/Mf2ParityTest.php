<?php

use App\Models\Article;
use App\Models\Note;
use App\Support\PortableText;
use Illuminate\Support\Arr;

/*
 * The .mf2 endpoint and the rendered page are two answers to the same
 * question. A parser that gets different ones from each has been lied to by
 * one of them: this holds both to one answer for the properties a consumer
 * actually reads off an entry.
 */

it('publishes the same h-entry, author and representative h-card through .mf2 as the page renders', function () {
    $article = Article::factory()->create([
        'status' => 'published',
        'title' => 'A titled piece',
        'occurred_at' => '2024-03-01 09:00:00',
        'content' => PortableText::fromPlainText('The body of the piece.'),
    ]);

    $page = microformatsOf($article->url());
    $export = json_decode($this->get($article->url().'.mf2')->getContent(), true);

    // Items are found by type, not index: the page yields [h-card, h-entry],
    // the export [h-entry, h-card]. Order is not part of the mf2 data model.
    $pageEntry = microformatItem($page, 'h-entry');
    $exportEntry = microformatItem($export, 'h-entry');
    $pageCard = microformatItem($page, 'h-card');
    $exportCard = microformatItem($export, 'h-card');

    // The nested p-author: {name, url} only. 'lang' and 'value' are artifacts
    // the parser adds to an embedded h-card reference; they're not part of
    // the hand-authored export JSON, so they're stripped before comparing.
    expect(Arr::except($pageEntry['properties']['author'][0], ['lang', 'value']))
        ->toBe($exportEntry['properties']['author'][0]);

    // The representative h-card: photo, name, both urls (site root and the
    // GitHub profile), uid, note. 'lang' is the only parser artifact here.
    expect(Arr::except($pageCard, ['lang']))->toBe($exportCard);

    // rel="me" and its rel-urls entry. Page-chrome rels (icon, manifest,
    // apple-touch-icon, preload, modulepreload, stylesheet, canonical, and
    // the site-wide feed alternates) aren't compared: the export's rels
    // deliberately carry only format alternates and "me", and a browser-
    // rendered page carries build-tool and PWA tags that have nothing to do
    // with this entry. The alternate set itself isn't compared either: the
    // page links to every format including this one, while the export's own
    // trail excludes itself, so the two sets differ by design, not by drift.
    expect($page['rels']['me'])->toBe($export['rels']['me']);
    $meUrl = $export['rels']['me'][0];
    expect($page['rel-urls'][$meUrl])->toBe($export['rel-urls'][$meUrl]);

    // The properties most likely to drift.
    expect($exportEntry['properties']['name'])->toBe($pageEntry['properties']['name'])
        ->and($exportEntry['properties']['url'])->toBe($pageEntry['properties']['url'])
        ->and($exportEntry['properties']['uid'])->toBe($pageEntry['properties']['uid']);

    // A genuine standfirst (a hand-written excerpt) publishes on both: it's
    // the one case where a type does have real text distinct from its body.
    expect($exportEntry['properties']['summary'])->toBe($pageEntry['properties']['summary']);

    // published: the page renders a local offset string, the export an ISO
    // instant. Comparing the date portion checks they name the same moment
    // without requiring one side's formatting to match the other's.
    expect($exportEntry['properties']['published'][0])
        ->toStartWith(substr($pageEntry['properties']['published'][0], 0, 10));

    // e-content: the plain-text value is what a consumer treats as the
    // canonical content and matches exactly. The html is not compared
    // byte-for-byte: the page's markup carries Tailwind presentation classes
    // (e.g. class="max-w-prose" on the paragraph) that the export's plain
    // semantic HTML deliberately doesn't reproduce.
    expect($exportEntry['properties']['content'][0]['value'])
        ->toBe($pageEntry['properties']['content'][0]['value']);
});

// The factory always writes an excerpt, but real articles don't: six of six
// published articles have none. ArticleDetail.vue's p-summary is gated on
// entry.excerpt, so a real article renders no summary at all, and the export
// must not paper over the gap with a generated one.
it('withholds p-summary from an article with no excerpt in both, matching real data', function () {
    $article = Article::factory()->create([
        'status' => 'published',
        'title' => 'A titled piece with nothing written up front',
        'occurred_at' => '2024-03-03 09:00:00',
        'excerpt' => null,
        'content' => PortableText::fromPlainText('The body carries the whole thing.'),
    ]);

    $pageEntry = microformatItem(microformatsOf($article->url()), 'h-entry');
    $exportEntry = json_decode($this->get($article->url().'.mf2')->getContent(), true)['items'][0];

    expect($pageEntry['properties'])->not->toHaveKey('summary')
        ->and($exportEntry['properties'])->not->toHaveKey('summary');
});

// A note is content with no title and no standfirst of its own: its body is
// the entry. That is how a reader tells it from an article, and it is the
// single most valuable assertion in this file, because it is what stops a
// reader treating a note as a titled, summarised article.
it('withholds p-name and p-summary from a note in both, so neither parses as an article', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-02 09:00:00',
        'content' => PortableText::fromPlainText('Just a thought.'),
    ]);

    $pageEntry = microformatItem(microformatsOf($note->url()), 'h-entry');
    $exportEntry = json_decode($this->get($note->url().'.mf2')->getContent(), true)['items'][0];

    expect($pageEntry['properties'])->not->toHaveKey('name')
        ->and($pageEntry['properties'])->not->toHaveKey('summary')
        ->and($exportEntry['properties'])->not->toHaveKey('name')
        ->and($exportEntry['properties'])->not->toHaveKey('summary');
});

it('publishes the same u-photo and u-featured images through .mf2 as the page renders', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-04 09:00:00',
        'content' => PortableText::fromPlainText('Two photos.'),
    ]);
    $note->addMediaFromString(parityJpeg())->usingFileName('one.jpg')->toMediaCollection('photos');
    $note->addMediaFromString(parityJpeg())->usingFileName('two.jpg')->toMediaCollection('photos');

    $article = Article::factory()->create([
        'status' => 'published',
        'occurred_at' => '2024-03-05 09:00:00',
        'content' => PortableText::fromPlainText('With a cover.'),
    ]);
    $article->addMediaFromString(parityJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');

    $pageNote = microformatItem(microformatsOf($note->url()), 'h-entry');
    $exportNote = json_decode($this->get($note->url().'.mf2')->getContent(), true)['items'][0];
    $pageArticle = microformatItem(microformatsOf($article->url()), 'h-entry');
    $exportArticle = json_decode($this->get($article->url().'.mf2')->getContent(), true)['items'][0];

    expect($pageNote['properties']['photo'])->toHaveCount(2)
        ->and($exportNote['properties']['photo'])->toBe($pageNote['properties']['photo'])
        ->and($exportArticle['properties']['featured'])->toBe($pageArticle['properties']['featured']);
});

function parityJpeg(): string
{
    $image = imagecreatetruecolor(64, 48);
    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}
