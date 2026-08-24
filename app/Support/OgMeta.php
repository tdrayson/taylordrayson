<?php

namespace App\Support;

use App\Actions\Og\BuildEntryOgData;
use App\Data\CardData;
use App\Models\Article;
use App\Models\Media;
use App\Models\Project;
use App\Models\TimelineEntry;
use App\Presenters\EntryDescription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Per-view Open Graph and document metadata. Controllers hand one of these to
 * their Inertia page as `og`, and AppHead.vue renders the head from it.
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
    public static function fuelStory(): array
    {
        return self::make([
            'title' => 'The pandemic and a war, in my fuel receipts',
            'eyebrow' => 'Data Story',
            'heading' => 'The pandemic and a war, in my fuel receipts',
            'accent' => TypeColors::hex('fuel'),
            'description' => 'Seven years of pump receipts for one small car: a wild petrol-price rollercoaster, the miles quietly stacking up to almost twice around the Earth, and what it really costs to keep moving.',
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function foodStory(): array
    {
        return self::make([
            'title' => 'The most-logged thing in my diet is coffee',
            'eyebrow' => 'Data Story',
            'heading' => 'The most-logged thing in my diet is coffee',
            'accent' => TypeColors::hex('food'),
            'description' => 'An unbroken daily food diary since 2019, kept through a Crohn\'s diagnosis, surgery and the gym, by someone who finds the data far more interesting than the eating.',
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function flightStory(): array
    {
        return self::make([
            'title' => 'The year I flew somewhere new every month',
            'eyebrow' => 'Data Story',
            'heading' => 'The year I flew somewhere new every month',
            'accent' => TypeColors::hex('flight'),
            'description' => 'Every flight I can still find a record of: two a year for ages, then a brand-new European city every month, all the budget hops and one accidental A380, adding up to barely a week off the ground.',
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function stories(): array
    {
        return self::make([
            'title' => 'Data Stories',
            'eyebrow' => 'Data Stories',
            'heading' => 'Data stories',
            'description' => 'In-depth, living looks at the data I keep on myself: the long reads behind the numbers.',
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
    public static function more(): array
    {
        return self::make([
            'title' => 'More',
            'eyebrow' => 'Directory',
            'heading' => 'Everything else on this site',
            'description' => 'The full directory: every type I track, and every page that does not earn a spot in the sidebar.',
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function series(): array
    {
        return self::make([
            'title' => 'TV',
            'eyebrow' => 'TV',
            'heading' => 'Every series I have watched',
            'description' => 'Television by show rather than by episode, with what I have finished and what I am partway through.',
            'accent' => 'media',
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function flightMap(): array
    {
        return self::make([
            'title' => 'Flight map',
            'eyebrow' => 'Flights',
            'heading' => 'Every flight on one globe',
            'description' => 'Every flight I have taken drawn as a great-circle arc, filterable by year.',
            'accent' => 'flight',
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
    public static function onThisDay(Carbon $date): array
    {
        $label = $date->format('j F');

        return self::make([
            'title' => 'On this day',
            'eyebrow' => 'On This Day',
            'heading' => "On this day: {$label}",
            'description' => "Everything I've logged on {$label}, across every year.",
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
     * @param  string  $noun  The type's singular noun (e.g. "activity"), pluralised against the total.
     * @param  int  $total  How many entries the archive holds.
     * @return OgPayload
     */
    public static function archive(string $type, string $label, string $title, string $accentToken, bool $isTaxonomy, string $noun, int $total): array
    {
        return self::make([
            'title' => $isTaxonomy ? $title : "All {$title}",
            'eyebrow' => $label,
            'heading' => $isTaxonomy ? $title : (OgPhrases::pick("archive.{$type}", [], $type) ?? $title),
            'accent' => TypeColors::hex($accentToken),
            // A taxonomy title already names what it holds ("Ride activities"),
            // so it leads and the count follows; the index has no such phrase.
            'description' => $isTaxonomy
                ? sprintf('%s: all %s, newest first.', $title, number_format($total))
                : sprintf("All %s %s I've logged, newest first.", number_format($total), Str::plural($noun, $total)),
        ]);
    }

    /**
     * @param  string  $label  The type's display label (e.g. "Activities").
     * @param  string  $accentToken  The card accent token (e.g. "activity").
     * @return OgPayload
     */
    public static function stats(string $label, string $accentToken): array
    {
        return self::make([
            'title' => "{$label} stats",
            'eyebrow' => $label,
            'heading' => "{$label} stats",
            'accent' => TypeColors::hex($accentToken),
            'description' => 'The numbers behind my '.Str::lower($label).': totals, trends and records.',
        ]);
    }

    /**
     * @param  string  $name  The tag's display name.
     * @return OgPayload
     */
    /**
     * A hand-authored content page. The excerpt is the author's own summary and
     * always wins; without one the page's opening prose stands in, which beats
     * falling back to the site description on every untended page.
     *
     * @param  string|null  $content  The page body as plain text, used only when there is no excerpt.
     * @return OgPayload
     */
    public static function page(string $title, ?string $excerpt, ?string $content = null): array
    {
        $description = Text::excerpt($excerpt, 200) ?: Text::excerpt($content, 200);

        return self::make(array_filter([
            'title' => $title,
            'heading' => $title,
            'description' => $description,
        ], fn (?string $value): bool => $value !== null && $value !== ''));
    }

    /**
     * A TV show's own page, described by how much of it I have watched.
     *
     * @param  string  $title  The show's title.
     * @param  int  $episodes  How many episodes have been watched.
     * @param  int|null  $seasons  How many seasons those episodes span, when known.
     * @param  string|null  $span  The watch period (e.g. "Mar 2024 to Aug 2026").
     * @return OgPayload
     */
    public static function seriesShow(string $title, int $episodes, ?int $seasons, ?string $span): array
    {
        $across = $seasons ? sprintf(' across %s %s', $seasons, Str::plural('season', $seasons)) : '';
        $when = $span ? ", {$span}" : '';

        return self::make([
            'title' => $title,
            'eyebrow' => 'TV',
            'heading' => $title,
            'accent' => TypeColors::hex('media'),
            'description' => sprintf(
                "I've watched %s %s of %s%s%s.",
                number_format($episodes),
                Str::plural('episode', $episodes),
                $title,
                $across,
                $when,
            ),
        ]);
    }

    /**
     * @return OgPayload
     */
    public static function tags(): array
    {
        return self::make([
            'title' => 'Tags',
            'eyebrow' => 'Index',
            'heading' => 'Tags',
            'description' => 'Every topic across the site, most-used first.',
        ]);
    }

    /**
     * @param  string  $name  The tag's display name.
     * @param  int  $total  How many entries carry the tag, across every type.
     * @return OgPayload
     */
    public static function tag(string $name, int $total): array
    {
        return self::make([
            'title' => "Tagged {$name}",
            'eyebrow' => 'Tag',
            'heading' => "Tagged {$name}",
            'description' => sprintf(
                'Everything tagged %s: %s %s from across every type I track, newest first.',
                $name,
                number_format($total),
                Str::plural('entry', $total),
            ),
        ]);
    }

    public static function trips(): array
    {
        return self::make([
            'title' => 'Trips',
            'eyebrow' => 'Index',
            'heading' => 'Trips',
            'description' => 'Every trip, newest first.',
        ]);
    }

    public static function trip(string $title): array
    {
        return self::make([
            'title' => $title,
            'eyebrow' => 'Trip',
            'heading' => $title,
            'description' => "Everything I logged during {$title}.",
        ]);
    }

    /**
     * @param  TimelineEntry|null  $entry  The entry whose pre-rendered card to point at, or null when
     *                                     the model has no spine row (e.g. an unpublished article
     *                                     previewed by its author), in which case the OG image is omitted.
     * @param  Model  $model  The entry's content, which its description is written from.
     * @param  CardData  $card  The built card, for its title, subtitle and date.
     * @return OgPayload
     */
    public static function entry(?TimelineEntry $entry, Model $model, CardData $card): array
    {
        return self::make([
            'title' => self::entryTitle($model, $card),
            'description' => EntryDescription::for($model, $card),
            'image' => $entry !== null ? self::entryCardUrl($entry) : null,
            'type' => $model instanceof Article ? 'article' : 'website',
        ]);
    }

    /**
     * The entry's card URL, stamped with the card design and the entry's own
     * last-updated time.
     *
     * Both belong in the URL because the card is cached against both, and the
     * URL is what anyone holding a share preview refetches by. Without them a
     * redesigned or edited card keeps the address of the one it replaced.
     */
    public static function entryCardUrl(TimelineEntry $entry): string
    {
        return route('og.entry', $entry).'?'.http_build_query([
            'v' => OgRenderer::generation(),
            't' => BuildEntryOgData::entryTimestamp($entry),
        ]);
    }

    /**
     * A page title that identifies one entry among its type's thousands.
     *
     * Log entries repeat their titles heavily: 655 walks are all called "Walk",
     * and a search result listing them is useless. Dating them is what tells
     * one from another. Hand-authored pieces are already named deliberately, so
     * they keep the title as written.
     */
    private static function entryTitle(Model $model, CardData $card): string
    {
        if ($model instanceof Article || $model instanceof Project) {
            return $card->title;
        }

        // An episode's card title is the episode's alone, which off the show's
        // page names nothing: "Netherlands (Race)" needs "Formula 1" in front.
        $show = $model instanceof Media ? ShowTitle::for($model) : null;
        $title = $show !== null ? "{$show}: {$card->title}" : $card->title;

        return Text::excerpt($title, 60).', '.$card->occurredAt->format('j F Y');
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
