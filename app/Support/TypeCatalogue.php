<?php

namespace App\Support;

use App\Data\TypeMeta;
use App\Enums\TimelineType;

/**
 * How every data type is drawn and named: icon, labels, archive href, accent
 * token and palette keywords, in one table.
 *
 * This is the structural half of the pair; TypeColors is the colour half, and
 * reads the hues themselves out of the stylesheet. Between them nothing about a
 * type is written down twice.
 *
 * The frontend gets this table as resources/js/types.generated.js, written by
 * `php artisan types:sync` and kept honest by tests/Feature/TypeCatalogueTest.php.
 */
final class TypeCatalogue
{
    /** Where the generated frontend module is written. */
    public const MODULE = 'resources/js/types.generated.js';

    /** @var array<string, TypeMeta>|null */
    private static ?array $all = null;

    /**
     * The 13 timeline data types, in the order they were introduced.
     *
     * @return list<TypeMeta>
     */
    public static function timeline(): array
    {
        return [
            self::type(TimelineType::Activity, 'WorkoutRunIcon', 'Activity', 'Activities', '/activities', 'workout exercise sport'),
            self::type(TimelineType::Sleep, 'Moon02Icon', 'Sleep', 'Sleep', '/sleep', 'rest bed nap'),
            self::type(TimelineType::Calorie, 'UtensilsIcon', 'Food', 'Food', '/food', 'food eat meal nutrition'),
            self::type(TimelineType::Media, 'Film01Icon', 'Media', 'Media', '/media', 'watch movie film tv show book reading'),
            self::type(TimelineType::Event, 'Ticket01Icon', 'Event', 'Events', '/events', 'ticket gig concert'),
            self::type(TimelineType::Appearance, 'Mic01Icon', 'Appearance', 'Appearances', '/appearances', 'talk speaking interview'),
            self::type(TimelineType::Podcast, 'PodcastIcon', 'This Week With', 'This Week With', '/this-week-with', 'podcast tww episode'),
            self::type(TimelineType::Flight, 'AirplaneTakeOff01Icon', 'Flight', 'Flights', '/flights', 'fly travel trip airport'),
            // The only type whose OG eyebrow is its plural: a place card leads
            // with the section it belongs to, not with "Place".
            self::type(TimelineType::Checkin, 'Location01Icon', 'Place', 'Places', '/places', 'place location visited', 'Places'),
            self::type(TimelineType::Fuel, 'PetrolPumpIcon', 'Fuel', 'Fuel', '/fuel', 'petrol gas diesel'),
            self::type(TimelineType::Project, 'RocketIcon', 'Project', 'Projects', '/projects', 'build side product'),
            self::type(TimelineType::Article, 'File01Icon', 'Article', 'Articles', '/articles', 'blog post writing read'),
            self::type(TimelineType::Note, 'StickyNote02Icon', 'Note', 'Notes', '/notes', 'memo journal thought'),
        ];
    }

    /**
     * The other things an internal link can point at, so a link preview has a
     * glyph for each. See App\Links\Resolvers. Their `href` is where a link of
     * that kind goes, not an archive, so they carry no plural and stay out of
     * the palette's Archives list.
     *
     * @return list<TypeMeta>
     */
    public static function links(): array
    {
        return [
            new TypeMeta(key: 'story', icon: 'BookOpen01Icon', label: 'Story', accent: 'article', href: '/stories'),
            new TypeMeta(key: 'page', icon: 'File02Icon', label: 'Page', accent: 'article', plural: 'Pages'),
            new TypeMeta(key: 'period', icon: 'Calendar03Icon', label: 'Archive', accent: 'article', href: '/'),
            new TypeMeta(key: 'tag', icon: 'Tag01Icon', label: 'Tag', accent: 'article', href: '/tags'),
            new TypeMeta(key: 'live', icon: 'Clock01Icon', label: 'Now', accent: 'activity', href: '/now'),
        ];
    }

    /**
     * Types that exist only as something to write. A book is a Media row
     * narrowed to MediaType::Book, so it takes the media hue.
     *
     * @return list<TypeMeta>
     */
    public static function authoring(): array
    {
        return [
            new TypeMeta(key: 'book', icon: 'BookOpen01Icon', label: 'Book', accent: 'media'),
        ];
    }

    /**
     * Every type a card, link preview, icon lookup or /new form can be handed.
     *
     * @return array<string, TypeMeta>
     */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }

        $all = [];

        foreach ([...self::timeline(), ...self::links(), ...self::authoring()] as $meta) {
            $all[$meta->key] = $meta;
        }

        return self::$all = $all;
    }

    public static function for(string $key): ?TypeMeta
    {
        return self::all()[$key] ?? null;
    }

    /**
     * The row for a timeline type, which the catalogue is guaranteed to hold:
     * every case is declared in timeline() above, and a test asserts it.
     */
    public static function forType(TimelineType $type): TypeMeta
    {
        return self::all()[$type->value];
    }

    /**
     * The contents of the generated frontend module. Returned as a string rather
     * than an array so the sync test can compare the committed bytes: comparing
     * decoded values would pass while the file on disk differed.
     */
    public static function module(): string
    {
        $groups = [
            'timelineTypes' => self::timeline(),
            'linkTypes' => self::links(),
            'authoringTypes' => self::authoring(),
        ];

        $blocks = [];

        foreach ($groups as $name => $types) {
            $rows = array_map(
                fn (TypeMeta $meta): string => sprintf('    %s: %s,', self::property($meta->key), self::literal($meta->toArray())),
                $types,
            );

            $blocks[] = sprintf("export const %s = {\n%s\n};", $name, implode("\n", $rows));
        }

        return implode("\n\n", [self::header(), ...$blocks])."\n";
    }

    private static function header(): string
    {
        return <<<'JS'
            // Generated by `php artisan types:sync`. Do not edit.
            //
            // The single source of truth is App\Support\TypeCatalogue. Colours are not
            // here: `accent` is a --color-* token key, defined in resources/css/theme.css
            // and resources/css/dark.css so a type's hue follows the reader's scheme.
            JS;
    }

    /**
     * A key as a JS object property, quoted only when it is not a bare identifier.
     * Every key today is one, so this changes nothing until a hyphenated type key
     * appears and would otherwise emit a module that will not parse.
     */
    private static function property(string $key): string
    {
        return preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $key) === 1 ? $key : "'".$key."'";
    }

    /**
     * A JS object literal, single-quoted to match the hand-written modules.
     *
     * @param  array<string, string>  $values
     */
    private static function literal(array $values): string
    {
        $pairs = [];

        foreach ($values as $key => $value) {
            $pairs[] = sprintf("%s: '%s'", $key, str_replace(['\\', "'"], ['\\\\', "\\'"], $value));
        }

        return '{ '.implode(', ', $pairs).' }';
    }

    private static function type(TimelineType $type, string $icon, string $label, string $plural, string $href, string $keywords, ?string $eyebrow = null): TypeMeta
    {
        return new TypeMeta(
            key: $type->value,
            icon: $icon,
            label: $label,
            accent: $type->accent(),
            plural: $plural,
            href: $href,
            keywords: $keywords,
            eyebrow: $eyebrow,
        );
    }
}
