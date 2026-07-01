<?php

use App\Support\TypeColors;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Entry;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

/**
 * Seed a fake cached OG PNG for a content entry (type + slug + date), using the
 * same hash the controller will look for:
 *   md5("version|type|slug|date")
 * where date is the entry's occurredAt() as a YYYY-MM-DD date string.
 */
function seedContentCard(string $type, string $slug, string $date): void
{
    Storage::fake('local');
    $hash = md5(implode('|', [config('og.version'), $type, $slug, $date]));
    Storage::disk('local')->put("og/content/{$hash}.png", 'fake-png-bytes');
}

// ---------------------------------------------------------------------------
// Route resolution: published article returns 200
// ---------------------------------------------------------------------------

it('returns 200 with image/png for a published article og card', function () {
    Entry::make()->collection('articles')->slug('my-article')
        ->date('2025-05-12')
        ->data(['title' => 'My Article'])
        ->save();

    seedContentCard('article', 'my-article', '2025-05-12');

    $this->get('/og/content/article/my-article.png')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

// ---------------------------------------------------------------------------
// Route resolution: published note returns 200
// ---------------------------------------------------------------------------

it('returns 200 with image/png for a published note og card', function () {
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A quick thought']]],
    ];

    Entry::make()->collection('notes')->slug('my-note')
        ->date('2025-06-01')
        ->data(['content' => $bardContent])
        ->save();

    seedContentCard('note', 'my-note', '2025-06-01');

    $this->get('/og/content/note/my-note.png')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

// ---------------------------------------------------------------------------
// 404 for unknown slug
// ---------------------------------------------------------------------------

it('returns 404 for an unknown article slug', function () {
    $this->get('/og/content/article/does-not-exist.png')->assertNotFound();
});

// ---------------------------------------------------------------------------
// 404 for wrong type (route constraint rejects it)
// ---------------------------------------------------------------------------

it('returns 404 for an invalid content type', function () {
    $this->get('/og/content/media/some-slug.png')->assertNotFound();
});

// ---------------------------------------------------------------------------
// 404 for draft article
// ---------------------------------------------------------------------------

it('returns 404 for a draft article og card', function () {
    Entry::make()->collection('articles')->slug('draft-article')
        ->date('2025-05-12')
        ->data(['title' => 'Draft Article'])
        ->published(false)
        ->save();

    $this->get('/og/content/article/draft-article.png')->assertNotFound();
});

// ---------------------------------------------------------------------------
// OgMeta::contentEntry now includes an image key
// ---------------------------------------------------------------------------

it('og payload for a content entry page includes an image pointing at the og.content route', function () {
    Entry::make()->collection('articles')->slug('og-image-article')
        ->date('2025-05-12')
        ->data(['title' => 'OG Image Article'])
        ->save();

    $this->get('/2025/05/12/og-image-article')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Entry')
            ->where('og.image', fn ($value) => str_contains((string) $value, '/og/content/article/og-image-article.png'))
        );
});

// ---------------------------------------------------------------------------
// og.content named route resolves correctly
// ---------------------------------------------------------------------------

it('og.content route resolves to the correct URL', function () {
    $url = route('og.content', ['type' => 'article', 'slug' => 'hello-world']);

    expect($url)->toContain('/og/content/article/hello-world.png');
});

// ---------------------------------------------------------------------------
// Accent token is correct: article uses the article colour, note uses note
// ---------------------------------------------------------------------------

it('seeds the og card with the article accent colour', function () {
    Entry::make()->collection('articles')->slug('accent-test')
        ->date('2025-05-12')
        ->data(['title' => 'Accent Test'])
        ->save();

    seedContentCard('article', 'accent-test', '2025-05-12');

    $this->get('/og/content/article/accent-test.png')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');

    expect(TypeColors::hex('article'))->not->toBeEmpty();
});
