<?php

namespace App\Support;

use App\Models\TimelineEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The single source of per-view Open Graph / document metadata. Every controller
 * hands one of these arrays to its Inertia page as `og`, and AppHead.vue renders
 * the document head from it. The gallery preview builds its sample cards from the
 * same methods, so the copy lives in exactly one place.
 *
 * Shape (see {@see make()}):
 *
 * @phpstan-type OgPayload array{
 *     title: ?string,
 *     description: string,
 *     heading: ?string,
 *     eyebrow: ?string,
 *     accent: ?string,
 *     image: ?string,
 *     variant: ?string,
 *     type: string,
 *     noindex: bool,
 * }
 */
class OgMeta
{
    private const SITE_DESCRIPTION = 'I build things on the internet, track everything, and drink too much coffee. A living archive of what I make, watch, read, and get up to.';

    /**
     * @return OgPayload
     */
    public static function timeline(): array
    {
        return self::make([
            'title' => 'Timeline',
            'heading' => 'Taylor Drayson',
            'description' => 'Everything I log, in one continuous feed, newest first.',
            'variant' => 'home',
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function now(): array
    {
        return self::make([
            'title' => 'Now',
            'eyebrow' => 'Now',
            'heading' => "What I'm up to right now",
            'description' => "A live snapshot of my world: the time where I am, the weather, what I'm reading, and more.",
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function search(): array
    {
        return self::make([
            'title' => 'Search',
            'eyebrow' => 'Search',
            'heading' => "Find anything I've logged",
            'description' => "Search everything I've logged, by type, tag, place, and more.",
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function gallery(): array
    {
        return self::make([
            'title' => 'Photos',
            'eyebrow' => 'Photos',
            'heading' => 'Every photo, in one place',
            'description' => "A gallery of the photos from everything I've logged, newest first.",
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function feeds(): array
    {
        return self::make([
            'title' => 'Feeds',
            'eyebrow' => 'Feeds',
            'heading' => 'Follow along in your reader',
            'description' => 'Follow along in your feed reader. Pick a ready-made mix or build a custom feed of exactly the types you want.',
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function leaderboard(): array
    {
        return self::make([
            'title' => 'Leaderboard',
            'eyebrow' => '404 Snake',
            'heading' => 'Top scores on the leaderboard',
            'description' => 'High scores from the 404 Snake game.',
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function designSystem(): array
    {
        return self::make([
            'title' => 'Design system',
            'description' => 'The components, tokens, and patterns behind this site.',
            'noindex' => true,
        ]);
    }

    /**
     * @param  int  $status  The HTTP status code (e.g. 404).
     * @return OgPayload
     */
    public static function error(int $status): array
    {
        return self::make([
            'title' => "{$status} Not Found",
            'description' => 'The page you were looking for does not exist.',
            'noindex' => true,
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function year(int $year): array
    {
        return self::make([
            'title' => (string) $year,
            'eyebrow' => 'The Year',
            'heading' => OgPhrases::pick('year', ['date' => (string) $year], (string) $year),
            'description' => "My {$year}: activities, places, flights, films, and everything else I tracked.",
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function month(int $year, int $month): array
    {
        $label = Carbon::create($year, $month, 1)->format('F Y');

        return self::make([
            'title' => $label,
            'eyebrow' => 'The Month',
            'heading' => OgPhrases::pick('month', ['date' => $label], "{$year}-{$month}"),
            'description' => "Everything I logged in {$label}.",
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function day(Carbon $date): array
    {
        $label = $date->format('j F Y');

        return self::make([
            'title' => $label,
            'eyebrow' => 'The Day',
            'heading' => OgPhrases::pick('day', ['date' => $label], $date->format('Y-n-j')),
            'description' => "Everything I logged on {$label}.",
        ]);
    }

    /**
     * @param  string  $label  The type's display label (e.g. "Places"), shown as the eyebrow.
     * @param  string  $title  The page title (the type label, or a taxonomy phrase).
     * @param  string  $accentToken  The card accent token (e.g. "checkin", "food").
     * @param  bool  $isTaxonomy  Whether this is a taxonomy sub-page rather than the index.
     * @param  string|null  $subtitle  The count line used as the meta description.
     * @return OgPayload
     */
    public static function archive(string $type, string $label, string $title, string $accentToken, bool $isTaxonomy, ?string $subtitle): array
    {
        return self::make([
            'title' => $title,
            'eyebrow' => $label,
            'heading' => $isTaxonomy ? $title : (OgPhrases::pick("archive.{$type}", [], $type) ?? $title),
            'accent' => TypeColors::hex($accentToken),
            'description' => $subtitle ?: 'All my '.Str::lower($title).'.',
        ]);
    }

    /**
     * @param  TimelineEntry  $entry  The entry whose pre-rendered card to point at.
     * @param  string  $title  The entry's display title.
     * @return OgPayload
     */
    public static function entry(TimelineEntry $entry, string $title): array
    {
        return self::make([
            'title' => $title,
            'description' => $title,
            'image' => route('og.entry', $entry),
        ]);
    }

    /**
     * Fill a partial payload with the shared defaults.
     *
     * @param  array{title?: ?string, description?: string, heading?: ?string, eyebrow?: ?string, accent?: ?string, image?: ?string, variant?: ?string, type?: string, noindex?: bool}  $attributes
     * @return OgPayload
     */
    private static function make(array $attributes): array
    {
        return array_merge([
            'title' => null,
            'description' => self::SITE_DESCRIPTION,
            'heading' => null,
            'eyebrow' => null,
            'accent' => null,
            'image' => null,
            'variant' => null,
            'type' => 'website',
            'noindex' => false,
        ], $attributes);
    }
}
