<?php

use App\Data\TypeMeta;
use App\Models\Page;
use App\Support\TypeCatalogue;

use function Pest\Laravel\get;

/**
 * Every destination the command palette offers has to resolve.
 *
 * The palette's page list is hand-written in JavaScript, so nothing connected
 * it to the routes it names: /map, /calendar, /stats and /working-on sat in it
 * offering four 404s. Reading the hrefs back out of the module is what ties
 * the two together.
 *
 * @return list<string>
 */
function paletteHrefs(string $export): array
{
    $source = file_get_contents(resource_path('js/navigation.js'));
    $start = strpos($source, "export const {$export}");
    $body = substr($source, $start, strpos($source, '];', $start) - $start);

    preg_match_all("/href: '([^']+)'/", $body, $matches);

    return array_values(array_unique($matches[1]));
}

it('offers no page destination that 404s', function () {
    // The one hand-authored page the palette names; the rest are real routes.
    Page::factory()->create(['slug' => 'about', 'published' => true]);

    $hrefs = paletteHrefs('pageCommands');

    expect($hrefs)->not->toBeEmpty();

    foreach ($hrefs as $href) {
        expect(get($href)->getStatusCode())
            ->toBeLessThan(400, "Command palette links to {$href}, which does not resolve");
    }
});

it('offers no archive destination that 404s', function () {
    // Straight from the catalogue rather than the module it generates: the sync
    // test already proves the two agree, and an href is a route either way.
    $hrefs = array_values(array_unique(array_filter(
        array_map(fn (TypeMeta $meta): ?string => $meta->href, TypeCatalogue::all()),
    )));

    expect($hrefs)->not->toBeEmpty();

    foreach ($hrefs as $href) {
        expect(get($href)->getStatusCode())
            ->toBeLessThan(400, "The type catalogue links to {$href}, which does not resolve");
    }
});
