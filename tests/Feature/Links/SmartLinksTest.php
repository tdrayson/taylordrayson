<?php

use App\Actions\BuildLinkFavicons;
use App\Jobs\ResolveLinkFavicons;
use App\Links\LinkResolvers;
use App\Models\Article;
use App\Models\Note;
use App\Models\Page;
use App\Services\GoogleFavicons;
use App\Support\Links;
use App\Support\PortableText;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/** The spans of the first block, as [text, isLinked] pairs. */
function spansOf(array $document): array
{
    $block = $document[0];

    return array_map(
        fn (array $span): array => [$span['text'], $span['marks'] !== []],
        $block['children'],
    );
}

it('autolinks a bare URL sent as plain text', function () {
    // The editor autolinks as you type; this is the path a Shortcut, an import
    // or a Micropub client takes, which would otherwise leave dead text.
    $document = PortableText::fromPlainText('Read https://example.com/a-post today.');

    expect(spansOf($document))->toBe([
        ['Read ', false],
        ['https://example.com/a-post', true],
        [' today.', false],
    ])->and($document[0]['markDefs'][0]['href'])->toBe('https://example.com/a-post');
});

it('keeps sentence punctuation out of the linked address', function () {
    // A greedy match swallows the full stop and the closing bracket, which then
    // 404 when followed.
    $hrefs = fn (string $text): array => array_column(
        PortableText::fromPlainText($text)[0]['markDefs'], 'href',
    );

    expect($hrefs('See https://example.com/a.'))->toBe(['https://example.com/a'])
        ->and($hrefs('See (https://example.com/a) here'))->toBe(['https://example.com/a'])
        ->and($hrefs('See https://example.com/a_(b) here'))->toBe(['https://example.com/a_(b)']);
});

it('leaves text with no URL as a plain block', function () {
    $document = PortableText::fromPlainText('Nothing to link here.');

    expect($document[0]['markDefs'])->toBe([])
        ->and(spansOf($document))->toBe([['Nothing to link here.', false]]);
});

it('collects the external hosts a document links to', function () {
    $blocks = [[
        '_type' => 'block',
        'markDefs' => [
            ['_key' => 'a', '_type' => 'link', 'href' => 'https://www.example.com/one'],
            ['_key' => 'b', '_type' => 'link', 'href' => 'https://example.com/two'],
            ['_key' => 'c', '_type' => 'link', 'href' => 'https://other.test/x'],
            ['_key' => 'd', '_type' => 'link', 'href' => '/2026/08/20/a-note'],
        ],
        'children' => [],
    ]];

    // www is stripped, so one domain resolves to one favicon; internal hrefs
    // are not external hosts.
    expect(Links::hostsIn($blocks))->toBe(['example.com', 'other.test']);
});

it('maps only the hosts whose favicon has actually been stored', function () {
    $path = Links::faviconPath('stored.test');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'png-bytes');

    $blocks = [[
        '_type' => 'block',
        'markDefs' => [
            ['_key' => 'a', '_type' => 'link', 'href' => 'https://stored.test/x'],
            ['_key' => 'b', '_type' => 'link', 'href' => 'https://missing.test/y'],
        ],
        'children' => [],
    ]];

    // A host with no file is absent rather than null, so the renderer falls
    // through to the globe instead of waiting on a download.
    expect((new BuildLinkFavicons)($blocks))->toBe(['stored.test' => '/favicons/stored.test.png']);

    File::delete($path);
});

it('treats a refused favicon as no favicon', function () {
    // Google answers an unknown domain with a generic globe rather than a 404,
    // so a refusal is the only "no icon" this can tell apart. A globe is stored
    // as a real icon, which beats the blank the previous service returned.
    Http::fake([
        'www.google.com/s2/favicons*' => Http::response('', 404),
    ]);

    expect((new GoogleFavicons)->icon('nowhere.test')['status'])->toBe('unavailable');
});

it('treats a non-image answer as an error rather than storing it', function () {
    Http::fake([
        'www.google.com/s2/favicons*' => Http::response('<html>nope</html>', 200, ['content-type' => 'text/html']),
    ]);

    expect((new GoogleFavicons)->icon('example.com')['status'])->toBe('error');
});

it('returns the bytes for a real favicon', function () {
    Http::fake([
        'www.google.com/s2/favicons*' => Http::response(str_repeat('a', 400), 200, ['content-type' => 'image/png']),
    ]);

    expect((new GoogleFavicons)->icon('example.com'))
        ->status->toBe('saved')
        ->body->toHaveLength(400);
});

it('queues a favicon fetch when an entry is saved with a new external link', function () {
    Queue::fake();

    $note = Note::factory()->create([
        'content' => PortableText::fromPlainText('Read https://example.com/a-post today.'),
    ]);

    Queue::assertPushed(ResolveLinkFavicons::class, fn ($job): bool => $job->hosts === ['example.com']);

    // Re-saving must not re-queue the same download once it is stored.
    $path = Links::faviconPath('example.com');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'png-bytes');

    Queue::fake();
    $note->touch();
    Queue::assertNothingPushed();

    File::delete($path);
});

it('does not queue anything for an entry with no external links', function () {
    // Guards the whole suite: entries are created constantly in tests, and a
    // dispatch on every one would mean real requests under the sync queue.
    Queue::fake();

    Note::factory()->create(['content' => PortableText::fromPlainText('Just a thought.')]);

    Queue::assertNothingPushed();
});

it('resolves each kind of internal destination to its own card', function () {
    Article::factory()->create(['occurred_at' => '2026-05-04 09:00:00', 'published' => true]);

    $resolve = fn (string $path): ?array => app(LinkResolvers::class)->resolve($path)?->toArray();

    expect($resolve('/stories/fuel'))->type->toBe('story')
        ->and($resolve('/flights'))->type->toBe('flight')
        ->and($resolve('/now'))->type->toBe('live')
        ->and($resolve('/2026/05'))->type->toBe('period')
        ->and($resolve('/2026'))->type->toBe('period');

    // An archive says how much is behind it, which is the point of previewing
    // a page that is a list rather than a thing.
    expect($resolve('/flights')['excerpt'])->toContain('flight')
        ->and($resolve('/2026/05')['title'])->toBe('May 2026');
});

it('gives back nothing for a path no resolver owns', function () {
    $resolve = fn (string $path): ?array => app(LinkResolvers::class)->resolve($path)?->toArray();

    expect($resolve('/stories/nonexistent'))->toBeNull()
        ->and($resolve('/tags/not-a-tag'))->toBeNull()
        ->and($resolve('/1999/01'))->toBeNull()
        ->and($resolve('/design-system'))->toBeNull();
});

it('lets a literal route win over the page catch-all', function () {
    // PageResolver matches any single lowercase segment, so a Page whose slug
    // collides with a real route must not shadow it.
    Page::factory()->create(['slug' => 'now', 'title' => 'Not the Now page', 'published' => true]);

    expect(app(LinkResolvers::class)->resolve('/now')->type)->toBe('live');
});
