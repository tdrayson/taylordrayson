<?php

namespace App\Http\Controllers;

use App\Data\CardData;
use App\Data\SegmentData;
use App\Models\Concerns\Timelineable;
use App\Models\Flight;
use App\Models\TimelineEntry;
use App\Support\OgMeta;
use App\Support\OgPhrases;
use App\Support\StaticMap;
use App\Support\TypeColors;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OgImageController extends Controller
{
    private const ACCENT_DEFAULT = '3858e9';

    private const TAGLINE = 'A living archive of everything I make, watch, read, and get up to.';

    /**
     * Card type to the eyebrow label shown on its OG card.
     *
     * @var array<string, string>
     */
    private const TYPE_EYEBROWS = [
        'activity' => 'Activity',
        'flight' => 'Flight',
        'checkin' => 'Places',
        'media' => 'Media',
        'sleep' => 'Sleep',
        'calorie' => 'Food',
        'fuel' => 'Fuel',
        'note' => 'Note',
        'article' => 'Article',
        'project' => 'Project',
        'event' => 'Event',
        'appearance' => 'Appearance',
        'podcast' => 'This Week With',
    ];

    /**
     * Sleep stage bar colours, mirroring the --color-sleep-* tokens in
     * resources/css/app.css (hsl converted to hex for the server-rendered card).
     *
     * @var array<string, string>
     */
    private const SLEEP_STAGE_COLORS = [
        'awake' => '#ea8686',
        'rem' => '#5494d4',
        'light' => '#9fbfdf',
        'deep' => '#5247c2',
    ];

    /**
     * Render (and cache) a 1200x630 Open Graph card for the given title.
     *
     * Input is read leniently so a malformed share URL still returns a valid
     * default card rather than an error. Cards are cached by a hash of their
     * inputs and regenerated only when missing.
     */
    public function show(Request $request): BinaryFileResponse
    {
        $title = Str::limit(trim((string) $request->query('title')) ?: 'Taylor Drayson', 160, '');
        $eyebrow = Str::limit(trim((string) $request->query('eyebrow')), 60, '') ?: null;
        $date = Str::limit(trim((string) $request->query('date')), 60, '') ?: null;
        $accent = $this->accent($request->query('accent'));
        $layout = $request->query('variant') === 'home' ? 'home' : 'text';

        $disk = Storage::disk('local');
        $path = 'og/'.md5(implode('|', [config('og.version'), $layout, $title, (string) $eyebrow, (string) $date, $accent])).'.png';

        if (! $disk->exists($path)) {
            $disk->makeDirectory('og');

            $card = [
                'layout' => $layout,
                'accent' => $accent,
                'eyebrow' => $eyebrow,
                'title' => $title,
                'date' => $date,
                'subtitle' => $layout === 'home' ? self::TAGLINE : null,
                'image' => null,
                'cutout' => $this->dataUri('taylor-cutout.png', 'image/png'),
            ];

            $this->screenshot(view('og.card', $card), $disk->path($path));
        }

        return $this->serve($disk->path($path), 'public, max-age=31536000, immutable');
    }

    /**
     * Render (and cache) the Open Graph card for a single timeline entry, built
     * from the entry's real data: type accent, eyebrow, title, date, and a
     * contextual image (route map, check-in marker, or cover) where one fits.
     *
     * Cached by entry id plus the model's last-updated stamp, so the same URL is
     * reused until the entry changes.
     */
    public function entry(TimelineEntry $entry): BinaryFileResponse
    {
        $card = $this->entryCardData($entry);

        abort_if($card === null, 404);

        $disk = Storage::disk('local');
        $path = 'og/entry/'.md5(implode('|', [config('og.version'), $entry->id, $this->entryTimestamp($entry)])).'.png';

        if (! $disk->exists($path)) {
            $disk->makeDirectory('og/entry');
            $this->screenshot(view('og.card', $card), $disk->path($path));
        }

        return $this->serve($disk->path($path), 'public, max-age=86400');
    }

    /**
     * Build the card view data for a single entry from its real data: type accent,
     * eyebrow, title, date, and a contextual image. Returns null when the entry has
     * no timelineable model.
     *
     * @return array<string, mixed>|null
     */
    private function entryCardData(TimelineEntry $entry): ?array
    {
        $model = $entry->timelineable;

        if (! $model instanceof Timelineable) {
            return null;
        }

        if ($model instanceof Flight) {
            $model->load('origin', 'destination');
        }

        $card = $model->card();
        $accent = TypeColors::hex($card->accent, self::ACCENT_DEFAULT);
        [$layout, $image] = $this->entryImage($model, $card, $accent);

        // Seed the wording with the id and last-updated stamp, so editing an
        // entry rerolls its phrase (matching when the card itself regenerates).
        $seed = $entry->id.'|'.$this->entryTimestamp($entry);

        return [
            'layout' => $layout,
            'accent' => $accent,
            'eyebrow' => self::TYPE_EYEBROWS[$card->type] ?? null,
            'title' => $this->entryTitle($model, $card, $seed),
            'date' => $entry->occurred_at->format('D j M Y'),
            'subtitle' => null,
            'image' => $image,
            'stages' => $card->type === 'sleep' ? $this->sleepStages($card->meta->segments ?? []) : null,
            'cutout' => $this->dataUri('taylor-cutout.png', 'image/png'),
        ];
    }

    /**
     * Build the sleep stage bar segments (label, colour, width percent) from the
     * card's per-stage seconds. Returns an empty array when there is no data.
     *
     * @param  list<SegmentData>  $segments
     * @return array<int, array{label: string, color: string, percent: float}>
     */
    private function sleepStages(array $segments): array
    {
        $total = array_sum(array_column($segments, 'seconds'));

        if ($total <= 0) {
            return [];
        }

        return array_map(fn (SegmentData $segment): array => [
            'label' => $segment->label,
            'color' => self::SLEEP_STAGE_COLORS[$segment->stage] ?? '#'.self::ACCENT_DEFAULT,
            'percent' => round($segment->seconds / $total * 100, 2),
        ], $segments);
    }

    /**
     * The card headline for an entry. Stat entries (sleep, food, fuel, podcast)
     * get a personable, varied phrase built from their real numbers; everything
     * else keeps its real title.
     */
    private function entryTitle(Model $model, CardData $card, string $seed): string
    {
        $phrase = match ($card->type) {
            'sleep' => OgPhrases::pick('sleep', ['duration' => str_replace(' sleep', '', $card->title)], $seed),
            'calorie' => OgPhrases::pick('food', ['kcal' => trim(str_replace('kcal', '', $card->title))], $seed),
            'fuel' => OgPhrases::pick('fuel', ['cost' => number_format((float) $model->cost, 2), 'litres' => $model->litres], $seed),
            'podcast' => $model->season_number && $model->episode_number
                ? OgPhrases::pick('podcast', ['season' => $model->season_number, 'episode' => $model->episode_number], $seed)
                : null,
            default => null,
        };

        return Str::limit($phrase ?? trim($card->title), 160, '');
    }

    /**
     * Choose the card layout and background image for an entry: a route map for
     * activities/flights, a marker for check-ins, a cover for media when one
     * exists, otherwise a plain gradient (text layout).
     *
     * @return array{0: string, 1: ?string} The [layout, image URL] pair.
     */
    private function entryImage(Model $model, CardData $card, string $accent): array
    {
        $type = $card->type;

        if ($type === 'activity') {
            if ($url = StaticMap::route($card->meta->polyline, $accent)) {
                return ['media', $url];
            }
        }

        if ($type === 'flight') {
            $route = $card->meta->route;
            $url = StaticMap::arc(
                $this->floatOrNull($route?->origin->lng),
                $this->floatOrNull($route?->origin->lat),
                $this->floatOrNull($route?->destination->lng),
                $this->floatOrNull($route?->destination->lat),
                $accent,
            );

            if ($url) {
                return ['media', $url];
            }
        }

        if ($type === 'checkin') {
            $latitude = $this->floatOrNull($model->latitude);
            $longitude = $this->floatOrNull($model->longitude);

            if ($latitude !== null && $longitude !== null) {
                return ['media', StaticMap::marker($longitude, $latitude, $accent)];
            }
        }

        if (in_array($type, ['media', 'article'], true) && method_exists($model, 'getFirstMediaUrl')) {
            $cover = $model->getFirstMediaUrl('cover');

            if ($cover !== '') {
                return ['cover', $cover];
            }
        }

        return ['text', null];
    }

    /**
     * The entry's last-updated timestamp (the timelineable model's, falling back
     * to the entry's), used to key the cache and seed the wording.
     */
    private function entryTimestamp(TimelineEntry $entry): int
    {
        return $entry->timelineable?->updated_at?->timestamp ?? $entry->updated_at?->timestamp ?? 0;
    }

    /**
     * Cast a value to a float, or null when it is not numeric.
     */
    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * TEMP: render (and cache) one sample card per data type for the gallery.
     * Cached by og version, so bump OG_VERSION (or run `og:clear`) to refresh
     * after a design tweak.
     */
    public function preview(string $type): BinaryFileResponse
    {
        $cards = $this->sampleCards();

        abort_unless(isset($cards[$type]), 404);

        $disk = Storage::disk('local');
        $path = 'og/preview/'.md5(config('og.version').'|'.$type).'.png';

        if (! $disk->exists($path)) {
            $disk->makeDirectory('og/preview');

            $card = array_merge($cards[$type], [
                'cutout' => $this->dataUri('taylor-cutout.png', 'image/png'),
            ]);

            $this->screenshot(view('og.card', $card), $disk->path($path));
        }

        return $this->serve($disk->path($path), 'public, max-age=86400');
    }

    /**
     * TEMP: a gallery page listing every OG card variation in one place: the
     * regularly-hit pages, the per-type archives, the data-type samples, and one
     * real entry per type that has data.
     *
     * The page itself renders nothing; it just references each card by its real
     * route as a lazily-loaded image, so every card renders in its own fast,
     * cached request rather than blocking one giant page render.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function gallery(): View
    {
        $sections = [
            'Pages' => $this->pageCardUrls(),
            'Archives' => $this->archiveCardUrls(),
            'Data types' => $this->sampleCardUrls(),
            'Real entries' => $this->entryCardUrls(),
        ];

        return view('og.gallery', ['sections' => $sections]);
    }

    /**
     * Card URLs (via /og.png) for the regularly-hit pages, built from the same
     * OgMeta the real pages use, so the gallery never drifts from production.
     *
     * @return array<int, array{label: string, url: string}>
     */
    private function pageCardUrls(): array
    {
        $pages = [
            ['label' => 'Home (/)', 'og' => OgMeta::timeline()],
            ['label' => 'Now (/now)', 'og' => OgMeta::now()],
            ['label' => 'Search (/search)', 'og' => OgMeta::search()],
            ['label' => 'Feeds (/feeds)', 'og' => OgMeta::feeds()],
            ['label' => 'Leaderboard (/leaderboard)', 'og' => OgMeta::leaderboard()],
            ['label' => 'Year (/2026)', 'og' => OgMeta::year(2026)],
            ['label' => 'Month (/2026/06)', 'og' => OgMeta::month(2026, 6)],
            ['label' => 'Day (/2026/06/26)', 'og' => OgMeta::day(Carbon::create(2026, 6, 26))],
        ];

        return array_map(fn (array $page): array => [
            'label' => $page['label'],
            'url' => $this->ogUrl($page['og']),
        ], $pages);
    }

    /**
     * Card URLs (via /og.png) for each per-type archive index, coloured by type.
     *
     * @return array<int, array{label: string, url: string}>
     */
    private function archiveCardUrls(): array
    {
        $cards = [];

        foreach (array_keys(config('og-phrases.archive', [])) as $type) {
            $label = self::TYPE_EYEBROWS[$type] ?? $type;
            $accentToken = $type === 'calorie' ? 'food' : $type;

            $cards[] = [
                'label' => $label,
                'url' => $this->ogUrl(OgMeta::archive($type, $label, $label, $accentToken, false, null)),
            ];
        }

        return $cards;
    }

    /**
     * Build the /og.png URL that renders a given OgMeta payload's card.
     *
     * @param  array<string, mixed>  $og
     */
    private function ogUrl(array $og): string
    {
        return route('og').'?'.http_build_query(array_filter([
            'variant' => $og['variant'] ?? null,
            'eyebrow' => $og['eyebrow'] ?? null,
            'title' => $og['heading'] ?? $og['title'] ?? null,
            'accent' => $og['accent'] ?? null,
        ]));
    }

    /**
     * Card URLs (via /og/preview) for each data-type sample.
     *
     * @return array<int, array{label: string, url: string}>
     */
    private function sampleCardUrls(): array
    {
        $cards = [];

        foreach ($this->sampleCards() as $type => $card) {
            $cards[] = [
                'label' => $card['eyebrow'] ?? $type,
                'url' => url("/og/preview/{$type}.png"),
            ];
        }

        return $cards;
    }

    /**
     * Card URLs (via /og/entry) for the latest real entry of each type that has data.
     *
     * @return array<int, array{label: string, url: string}>
     */
    private function entryCardUrls(): array
    {
        $cards = [];

        foreach (self::TYPE_EYEBROWS as $type => $label) {
            $model = TypeRegistry::find($type)['model'] ?? null;

            if ($model === null) {
                continue;
            }

            $id = TimelineEntry::query()
                ->where('timelineable_type', $model)
                ->latest('occurred_at')
                ->value('id');

            if ($id !== null) {
                $cards[] = ['label' => "Entry: {$label}", 'url' => route('og.entry', $id)];
            }
        }

        return $cards;
    }

    /**
     * Sample data for each data type, used by the temp gallery. `layout` is the
     * standardised template (media / cover / text); accent and eyebrow give each
     * type its unique identity.
     *
     * @return array<string, array<string, mixed>>
     */
    private function sampleCards(): array
    {
        return [
            'activity' => ['layout' => 'media', 'accent' => TypeColors::hex('activity'), 'eyebrow' => 'Activity', 'title' => 'Morning Walk', 'date' => 'Mon 9 Oct 2023, 9:08am', 'meta' => '3.2 mi, 42 min', 'image' => $this->sampleMap('activity')],
            'flight' => ['layout' => 'media', 'accent' => TypeColors::hex('flight'), 'eyebrow' => 'Flight', 'title' => 'London to New York', 'date' => 'Wed 14 Aug 2024', 'meta' => 'BA117, LHR to JFK', 'image' => $this->sampleMap('flight')],
            'checkin' => ['layout' => 'media', 'accent' => TypeColors::hex('checkin'), 'eyebrow' => 'Places', 'title' => 'Sanderstead Recreation Ground', 'date' => 'Sun 12 May 2024', 'meta' => null, 'image' => StaticMap::marker(-0.0726, 51.337, TypeColors::hex('checkin'))],
            'media' => ['layout' => 'cover', 'accent' => TypeColors::hex('media'), 'eyebrow' => 'Books', 'title' => 'Atomic Habits', 'date' => 'Fri 3 Jan 2025', 'meta' => 'James Clear', 'image' => 'https://covers.openlibrary.org/b/isbn/9780735211292-L.jpg'],
            'appearance' => ['layout' => 'text', 'accent' => TypeColors::hex('appearance'), 'eyebrow' => 'Appearance', 'title' => 'Building a Lifelog in Laravel', 'date' => 'Thu 6 Feb 2026', 'meta' => 'Laracon EU'],
            'podcast' => ['layout' => 'text', 'accent' => TypeColors::hex('podcast'), 'eyebrow' => 'This Week With', 'title' => 'Season 7, Episode 249', 'date' => 'Thu 19 Jun 2026', 'meta' => '21 min, Taylor & Gordon'],
            'article' => ['layout' => 'text', 'accent' => TypeColors::hex('article'), 'eyebrow' => 'Article', 'title' => 'Why I track absolutely everything', 'date' => 'Mon 12 May 2025', 'meta' => '6 min read'],
            'note' => ['layout' => 'text', 'accent' => TypeColors::hex('note'), 'eyebrow' => 'Note', 'title' => 'A quick thought on building in public', 'date' => 'Tue 24 Jun 2026', 'meta' => null],
            'project' => ['layout' => 'text', 'accent' => TypeColors::hex('project'), 'eyebrow' => 'Project', 'title' => 'taylordrayson.com', 'date' => null, 'meta' => 'Laravel, Inertia, Vue'],
            'event' => ['layout' => 'text', 'accent' => TypeColors::hex('event'), 'eyebrow' => 'Event', 'title' => 'Laracon EU', 'date' => 'Tue 28 Jan 2026', 'meta' => 'Amsterdam'],
            'sleep' => ['layout' => 'text', 'accent' => TypeColors::hex('sleep'), 'eyebrow' => 'Sleep', 'title' => 'I slept 7h 32m', 'date' => 'Wed 25 Jun 2026', 'stages' => $this->sleepStages([
                new SegmentData('Awake', 'awake', 1620),
                new SegmentData('REM', 'rem', 6480),
                new SegmentData('Light', 'light', 13320),
                new SegmentData('Deep', 'deep', 5700),
            ])],
            'calorie' => ['layout' => 'text', 'accent' => TypeColors::hex('food'), 'eyebrow' => 'Food', 'title' => 'I ate 2,140 kcal', 'date' => 'Sun 22 Jun 2026', 'meta' => null],
            'fuel' => ['layout' => 'text', 'accent' => TypeColors::hex('fuel'), 'eyebrow' => 'Fuel', 'title' => 'I put £62.40 of fuel in', 'date' => 'Sat 14 Jun 2026', 'meta' => null],
        ];
    }

    /**
     * A pre-generated sample map URL (route / arc) stashed under storage, with a
     * plain tile fallback. Real cards will build these per entry.
     */
    private function sampleMap(string $name): string
    {
        $disk = Storage::disk('local');
        $file = "og-samples/{$name}.txt";

        return $disk->exists($file)
            ? trim((string) $disk->get($file))
            : 'https://api.mapbox.com/styles/v1/mapbox/light-v11/static/-0.1,51.38,11/1200x630@2x?attribution=false&logo=false&access_token='.config('services.mapbox.token');
    }

    /**
     * A public image embedded as a data URI, so Browsershot needs no network fetch.
     */
    private function dataUri(string $file, string $mime): string
    {
        $path = public_path($file);

        return is_file($path) ? "data:{$mime};base64,".base64_encode((string) file_get_contents($path)) : '';
    }

    /**
     * A safe 6-digit hex colour (without the hash), or the brand default.
     */
    private function accent(?string $value): string
    {
        $hex = ltrim((string) $value, '#');

        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? strtolower($hex) : self::ACCENT_DEFAULT;
    }

    /**
     * Screenshot a rendered Blade view to a 1200x630 PNG file via Browsershot.
     */
    private function screenshot(View $view, string $path): void
    {
        $this->browsershot($view)->save($path);
    }

    /**
     * A Browsershot instance configured for a 1200x630 card, with any custom
     * node/chrome binaries applied.
     */
    private function browsershot(View $view): Browsershot
    {
        $browsershot = Browsershot::html($view->render())
            ->windowSize(1200, 630)
            ->waitUntilNetworkIdle()
            ->setScreenshotType('png');

        if ($nodeBinary = config('browsershot.node_binary')) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        if ($npmBinary = config('browsershot.npm_binary')) {
            $browsershot->setNpmBinary($npmBinary);
        }

        if ($chromePath = config('browsershot.chrome_path')) {
            $browsershot->setChromePath($chromePath);
        }

        return $browsershot;
    }

    private function serve(string $path, string $cacheControl): BinaryFileResponse
    {
        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => $cacheControl,
        ]);
    }
}
