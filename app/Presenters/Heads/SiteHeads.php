<?php

namespace App\Presenters\Heads;

use App\Data\Head\HeadData;
use App\Data\Head\ShareCardData;
use App\Support\OgPhrases;
use App\Support\OgRenderer;
use App\Support\TypeColors;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/** The heads of the site's own pages, each with its generated share card. */
final class SiteHeads
{
    public static function default(): HeadData
    {
        return self::make();
    }

    public static function timeline(): HeadData
    {
        return self::make(title: 'Timeline', heading: 'Taylor Drayson', variant: 'home');
    }

    public static function now(): HeadData
    {
        return self::make(
            title: 'Now',
            eyebrow: 'Now',
            heading: "What I'm up to right now",
            description: "A live snapshot of my world: the time where I am, the weather, what I'm reading, and more.",
        );
    }

    public static function search(): HeadData
    {
        return self::make(
            title: 'Search',
            eyebrow: 'Search',
            heading: "Find anything I've logged",
            description: "Search everything I've logged, by type, tag, place, and more.",
        );
    }

    public static function gallery(): HeadData
    {
        return self::make(
            title: 'Photos',
            eyebrow: 'Photos',
            heading: 'Every photo, in one place',
            description: "A gallery of the photos from everything I've logged, newest first.",
        );
    }

    public static function feeds(): HeadData
    {
        return self::make(
            title: 'Feeds',
            eyebrow: 'Feeds',
            heading: 'Follow along in your reader',
            description: 'Follow along in your feed reader. Pick a ready-made mix or build a custom feed of exactly the types you want.',
        );
    }

    public static function fuelStory(): HeadData
    {
        return self::make(
            title: 'The pandemic and a war, in my fuel receipts',
            eyebrow: 'Data Story',
            heading: 'The pandemic and a war, in my fuel receipts',
            accent: TypeColors::hex('fuel'),
            description: 'Seven years of pump receipts for one small car: a wild petrol-price rollercoaster, the miles quietly stacking up to almost twice around the Earth, and what it really costs to keep moving.',
        );
    }

    public static function foodStory(): HeadData
    {
        return self::make(
            title: 'The most-logged thing in my diet is coffee',
            eyebrow: 'Data Story',
            heading: 'The most-logged thing in my diet is coffee',
            accent: TypeColors::hex('food'),
            description: 'An unbroken daily food diary since 2019, kept through a Crohn\'s diagnosis, surgery and the gym, by someone who finds the data far more interesting than the eating.',
        );
    }

    public static function flightStory(): HeadData
    {
        return self::make(
            title: 'The year I flew somewhere new every month',
            eyebrow: 'Data Story',
            heading: 'The year I flew somewhere new every month',
            accent: TypeColors::hex('flight'),
            description: 'Every flight I can still find a record of: two a year for ages, then a brand-new European city every month, all the budget hops and one accidental A380, adding up to barely a week off the ground.',
        );
    }

    public static function stories(): HeadData
    {
        return self::make(
            title: 'Data Stories',
            eyebrow: 'Data Stories',
            heading: 'Data stories',
            description: 'In-depth, living looks at the data I keep on myself: the long reads behind the numbers.',
        );
    }

    public static function leaderboard(): HeadData
    {
        return self::make(
            title: 'Leaderboard',
            eyebrow: '404 Snake',
            heading: 'Top scores on the leaderboard',
            description: 'High scores from the 404 Snake game.',
        );
    }

    public static function more(): HeadData
    {
        return self::make(
            title: 'More',
            eyebrow: 'Directory',
            heading: 'Everything else on this site',
            description: 'The full directory: every type I track, and every page that does not earn a spot in the sidebar.',
        );
    }

    public static function tvShows(): HeadData
    {
        return self::make(
            title: 'TV shows',
            eyebrow: 'TV',
            heading: 'Every show I have watched',
            description: 'Television by show rather than by episode, with what I have finished and what I am partway through.',
            accent: 'tv-episode',
        );
    }

    /**
     * A TV show's own page, described by how much of it I have watched.
     *
     * @param  int  $episodes  How many episodes have been watched.
     * @param  int|null  $seasons  How many seasons those episodes span, when known.
     * @param  string|null  $span  The watch period (e.g. "Mar 2024 to Aug 2026").
     * @param  string|null  $image  The show's backdrop or poster, used as the share image.
     */
    public static function tvShow(string $title, int $episodes, ?int $seasons, ?string $span, ?string $image = null): HeadData
    {
        $across = $seasons ? sprintf(' across %s %s', $seasons, Str::plural('season', $seasons)) : '';
        $when = $span ? ", {$span}" : '';

        return self::make(
            title: $title,
            eyebrow: 'TV',
            heading: $title,
            accent: TypeColors::hex('tv-episode'),
            description: sprintf(
                "I've watched %s %s of %s%s%s.",
                number_format($episodes),
                Str::plural('episode', $episodes),
                $title,
                $across,
                $when,
            ),
            image: $image === null || str_starts_with($image, 'http') ? $image : url($image),
        );
    }

    public static function flightMap(): HeadData
    {
        return self::make(
            title: 'Flights map',
            eyebrow: 'Flights',
            heading: 'Every flight on one globe',
            description: 'Every flight I have taken drawn as a great-circle arc, filterable by year.',
            accent: 'flight',
        );
    }

    public static function designSystem(): HeadData
    {
        return self::make(
            title: 'Design system',
            description: 'The components, tokens, and patterns behind this site.',
            noindex: true,
        );
    }

    public static function hub(): HeadData
    {
        return self::make(title: 'HQ');
    }

    public static function drafts(): HeadData
    {
        return self::make(title: 'Drafts');
    }

    public static function newEntry(): HeadData
    {
        return self::make(title: 'New');
    }

    public static function moderation(): HeadData
    {
        return self::make(title: 'Moderation');
    }

    public static function signIn(): HeadData
    {
        return self::make(title: 'Sign in');
    }

    public static function authorise(): HeadData
    {
        return self::make(title: 'Authorise');
    }

    public static function unsubscribed(): HeadData
    {
        return self::make(title: 'Unsubscribed');
    }

    public static function deleted(): HeadData
    {
        return self::make(title: 'Deleted', description: 'This post has been deleted.', noindex: true);
    }

    /**
     * @param  int  $status  The HTTP status code (e.g. 404).
     */
    public static function error(int $status): HeadData
    {
        return self::make(
            title: "{$status} Not Found",
            description: 'The page you were looking for does not exist.',
            noindex: true,
            owner: "error-{$status}",
        );
    }

    public static function year(int $year): HeadData
    {
        return self::make(
            title: (string) $year,
            eyebrow: 'The Year',
            heading: OgPhrases::pick('year', ['date' => (string) $year], (string) $year),
            description: "My {$year}: activities, places, flights, films, and everything else I tracked.",
        );
    }

    public static function month(int $year, int $month): HeadData
    {
        $label = Carbon::create($year, $month, 1)->format('F Y');

        return self::make(
            title: $label,
            eyebrow: 'The Month',
            heading: OgPhrases::pick('month', ['date' => $label], "{$year}-{$month}"),
            description: "Everything I logged in {$label}.",
        );
    }

    public static function onThisDay(Carbon $date): HeadData
    {
        $label = $date->format('j F');

        return self::make(
            title: 'On this day',
            eyebrow: 'On This Day',
            heading: "On this day: {$label}",
            description: "Everything I've logged on {$label}, across every year.",
        );
    }

    public static function day(Carbon $date): HeadData
    {
        $label = $date->format('j F Y');

        return self::make(
            title: $label,
            eyebrow: 'The Day',
            heading: OgPhrases::pick('day', ['date' => $label], $date->format('Y-n-j')),
            description: "Everything I logged on {$label}.",
        );
    }

    /**
     * @param  string  $label  The type's display label (e.g. "Places"), shown as the eyebrow.
     * @param  string  $title  The page title (the type label, or a taxonomy phrase).
     * @param  string  $accentToken  The card accent token (e.g. "place", "food").
     * @param  bool  $isTaxonomy  Whether this is a taxonomy sub-page rather than the index.
     * @param  string  $noun  The type's singular noun (e.g. "activity"), pluralised against the total.
     * @param  int  $total  How many entries the archive holds.
     */
    public static function archive(string $type, string $label, string $title, string $accentToken, bool $isTaxonomy, string $noun, int $total): HeadData
    {
        return self::make(
            title: $isTaxonomy ? $title : "All {$title}",
            eyebrow: $label,
            heading: $isTaxonomy ? $title : (OgPhrases::pick("archive.{$type}", [], $type) ?? $title),
            accent: TypeColors::hex($accentToken),
            // A taxonomy title already names what it holds ("Ride activities"),
            // so it leads and the count follows; the index has no such phrase.
            description: $isTaxonomy
                ? sprintf('%s: all %s, newest first.', $title, number_format($total))
                : sprintf("All %s %s I've logged, newest first.", number_format($total), Str::plural($noun, $total)),
        );
    }

    /**
     * @param  string  $label  The type's display label (e.g. "Activities").
     * @param  string  $accentToken  The card accent token (e.g. "activity").
     */
    public static function stats(string $label, string $accentToken): HeadData
    {
        return self::make(
            title: "{$label} stats",
            eyebrow: $label,
            heading: "{$label} stats",
            accent: TypeColors::hex($accentToken),
            description: 'The numbers behind my '.Str::lower($label).': totals, trends and records.',
        );
    }

    public static function tags(): HeadData
    {
        return self::make(
            title: 'Tags',
            eyebrow: 'Index',
            heading: 'Tags',
            description: 'Every topic across the site, most-used first.',
        );
    }

    /**
     * @param  string  $name  The tag's display name.
     * @param  int  $total  How many entries carry the tag, across every type.
     */
    public static function tag(string $name, int $total): HeadData
    {
        return self::make(
            title: "Tagged {$name}",
            eyebrow: 'Tag',
            heading: "Tagged {$name}",
            description: sprintf(
                'Everything tagged %s: %s %s from across every type I track, newest first.',
                $name,
                number_format($total),
                Str::plural('entry', $total),
            ),
        );
    }

    public static function trips(): HeadData
    {
        return self::make(
            title: 'Trips',
            eyebrow: 'Index',
            heading: 'Trips',
            description: 'Every trip, newest first.',
        );
    }

    public static function trip(string $title): HeadData
    {
        return self::make(
            title: $title,
            eyebrow: 'Trip',
            heading: $title,
            description: "Everything I logged during {$title}.",
        );
    }

    /**
     * A head with the site defaults filled in, and the generated card as its
     * image unless it brings its own.
     *
     * @param  string|null  $owner  The card's owner, when it is not the requested path.
     */
    public static function make(
        ?string $title = null,
        ?string $description = null,
        ?string $heading = null,
        ?string $eyebrow = null,
        ?string $accent = null,
        ?string $variant = null,
        bool $noindex = false,
        ?string $image = null,
        ?string $owner = null,
    ): HeadData {
        $head = new HeadData(
            title: $title,
            description: $description ?? config('identity.bio'),
            noindex: $noindex,
            card: new ShareCardData($heading, $eyebrow, $accent, $variant),
        );

        return $head->with(image: $image ?? self::cardUrl($head, $owner ?? '/'.ltrim(request()->path(), '/')));
    }

    /**
     * The signed URL of a page's generated card. Signing stops the renderer
     * taking requests for cards no page publishes.
     *
     * @param  string  $owner  The page the card belongs to; it keeps only its latest card.
     */
    public static function cardUrl(HeadData $head, string $owner): string
    {
        return URL::signedRoute('og', array_filter([
            'for' => $owner,
            'title' => $head->card->heading ?? $head->title ?? config('identity.name'),
            'eyebrow' => $head->card->eyebrow,
            'accent' => $head->card->accent,
            'variant' => $head->card->variant,
            'description' => $head->description,
            'v' => OgRenderer::generation(),
        ]));
    }
}
