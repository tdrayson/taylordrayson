<?php

use App\Actions\BuildLinkPreviews;
use App\Models\Page;
use App\Support\Links;

/** A Portable Text document holding one link. */
function documentLinkingTo(string $href): array
{
    return [[
        '_type' => 'block',
        '_key' => 'b1',
        'style' => 'normal',
        'markDefs' => [['_key' => 'l1', '_type' => 'link', 'href' => $href]],
        'children' => [['_type' => 'span', '_key' => 's1', 'text' => $href, 'marks' => ['l1']]],
    ]];
}

it('reads a pasted absolute URL as the path it points at', function () {
    config(['app.url' => 'https://taylordrayson.test']);

    expect(Links::internalPath('https://taylordrayson.test/2026/08/22/a-ride'))->toBe('/2026/08/22/a-ride')
        ->and(Links::internalPath('/2026/08/22/a-ride'))->toBe('/2026/08/22/a-ride')
        ->and(Links::internalPath('https://taylordrayson.test'))->toBe('/')
        ->and(Links::internalPath('https://example.com/elsewhere'))->toBeNull()
        ->and(Links::internalPath('mailto:taylor@example.com'))->toBeNull()
        ->and(Links::internalPath('#anchor'))->toBeNull();
});

it('previews an entry linked by its full address, not just by path', function () {
    config(['app.url' => 'https://taylordrayson.test']);

    $page = Page::factory()->create(['slug' => 'colophon', 'title' => 'Colophon', 'published' => true]);

    $previews = app(BuildLinkPreviews::class)(documentLinkingTo('https://taylordrayson.test/'.$page->slug));

    // Keyed by the href as written, since that is what the renderer looks up.
    expect($previews)->toHaveKey('https://taylordrayson.test/colophon')
        ->and($previews['https://taylordrayson.test/colophon']['title'])->toBe('Colophon');
});

it('does not count a link back to this site as an external host', function () {
    config(['app.url' => 'https://taylordrayson.test']);

    $blocks = [
        ...documentLinkingTo('https://taylordrayson.test/about'),
        ...documentLinkingTo('https://example.com/elsewhere'),
    ];

    // Otherwise links:favicons downloads our own favicon as a third party's.
    expect(Links::hostsIn($blocks))->toBe(['example.com']);
});
