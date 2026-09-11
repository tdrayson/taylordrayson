<?php

use App\Data\TypeMeta;
use App\Enums\TimelineType;
use App\Support\TypeCatalogue;
use App\Support\TypeColors;
use App\Timeline\TypeRegistry;

/*
 * The catalogue is the one table describing how a data type is drawn, and three
 * of its columns are only meaningful because something outside PHP agrees with
 * them: the generated module the bundle imports, the icon registry the glyph
 * names index, and the stylesheet the accent tokens name. Each of those can fall
 * out of step silently, rendering a plausible-looking wrong thing rather than
 * failing, which is what these assert.
 */

it('has a committed frontend module matching the catalogue', function () {
    expect(file_get_contents(base_path(TypeCatalogue::MODULE)))
        ->toBe(TypeCatalogue::module(), 'The generated types module is stale. Run `php artisan types:sync`.');
});

it('describes every timeline type, with the accent the enum gives it', function () {
    foreach (TimelineType::cases() as $case) {
        $meta = TypeCatalogue::for($case->value);

        expect($meta)->not->toBeNull("{$case->value} is missing from the catalogue.")
            ->and($meta->accent)->toBe($case->accent())
            ->and($meta->plural)->not->toBeNull("{$case->value} has an archive, so it needs a plural.")
            ->and($meta->href)->not->toBeNull();
    }
});

it('gives every archive the href TypeRegistry routes it at', function () {
    // The pairing entryTypes.js used to promise in a docblock and nothing checked.
    foreach (TypeRegistry::all() as $key => $definition) {
        expect('/'.$definition['slug'])
            ->toBe(TypeCatalogue::for($key)->href, "{$key} is routed at /{$definition['slug']} but linked to as ".TypeCatalogue::for($key)->href);
    }
});

it('resolves a real colour for every accent it names', function () {
    foreach (TypeCatalogue::all() as $key => $meta) {
        expect(TypeColors::hex($meta->accent))
            ->not->toBe('3858e9', "The {$key} accent token (--color-{$meta->accent}) has no colour in theme.css.");
    }
});

it('names only icons the frontend registry can resolve', function () {
    $registry = file_get_contents(base_path('resources/js/icons.js'));

    foreach (TypeCatalogue::all() as $key => $meta) {
        expect($registry)->toMatch(
            '/\b'.preg_quote($meta->icon, '/').'\b/',
            "{$key} asks for {$meta->icon}, which is not imported in resources/js/icons.js.",
        );
    }
});

it('gives every data-type accent a dark-mode value too', function () {
    $tokens = function (string $file): array {
        preg_match_all('/--color-([a-z0-9-]+):\s*hsl\(/i', file_get_contents(base_path($file)), $matches);

        return $matches[1];
    };

    $accents = array_map(fn (TypeMeta $meta): string => $meta->accent, TypeCatalogue::timeline());

    expect(array_diff($accents, $tokens('resources/css/dark.css')))
        ->toBeEmpty('A data-type accent is defined in theme.css but not dark.css, so it keeps its light hue on dark.');
});

it('has Open Graph phrasing for every timeline type', function () {
    // OgGalleryUrls renders a sample archive card per type; a type with no
    // phrases renders an empty one rather than failing.
    foreach (TimelineType::cases() as $case) {
        expect(config("og-phrases.archive.{$case->value}"))
            ->not->toBeNull("og-phrases.php has no archive entry for {$case->value}.");
    }
});
