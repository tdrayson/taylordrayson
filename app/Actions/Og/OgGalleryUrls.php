<?php

namespace App\Actions\Og;

use App\Data\SegmentData;
use App\Models\TimelineEntry;
use App\Support\OgMeta;
use App\Support\OgRenderer;
use App\Support\StaticMap;
use App\Support\TypeColors;
use App\Timeline\TypeRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Build the card URLs shown on the temp OG gallery/preview pages: the
 * regularly-hit pages, the per-type archives, the data-type samples, and one
 * real entry per type that has data. Also owns the small rendering-adjacent
 * helpers (accent sanitising, embedding a public file as a data URI) shared
 * by the controller's show/entry/preview routes.
 */
final class OgGalleryUrls
{
    public function __construct(
        private readonly BuildEntryOgData $entryOgData,
    ) {}

    /**
     * Every gallery section: the regularly-hit pages, the per-type archives,
     * the data-type samples, and one real entry per type that has data.
     *
     * @return array<string, array<int, array{label: string, url: string}>>
     */
    public function sections(): array
    {
        return [
            'Pages' => $this->pageCardUrls(),
            'Archives' => $this->archiveCardUrls(),
            'Data types' => $this->sampleCardUrls(),
            'Real entries' => $this->entryCardUrls(),
        ];
    }

    /**
     * Sample data for each data type, used by the temp gallery and the
     * `/og/preview/{type}` route. `layout` is the standardised template
     * (media / cover / text); accent and eyebrow give each type its unique
     * identity.
     *
     * @return array<string, array<string, mixed>>
     */
    public function sampleCards(): array
    {
        return [
            'activity' => ['layout' => 'media', 'accent' => TypeColors::hex('activity'), 'eyebrow' => 'Activity', 'title' => 'Morning Walk', 'date' => 'Mon 9 Oct 2023, 9:08am', 'meta' => '3.2 mi, 42 min', 'image' => $this->sampleMap('activity')],
            'flight' => ['layout' => 'media', 'accent' => TypeColors::hex('flight'), 'eyebrow' => 'Flight', 'title' => 'London to New York', 'date' => 'Wed 14 Aug 2024', 'meta' => 'BA117, LHR to JFK', 'image' => $this->sampleMap('flight')],
            'checkin' => ['layout' => 'media', 'accent' => TypeColors::hex('checkin'), 'eyebrow' => 'Places', 'title' => 'Sanderstead Recreation Ground', 'date' => 'Sun 12 May 2024', 'meta' => null, 'image' => StaticMap::marker(-0.0726, 51.337, TypeColors::hex('checkin'))],
            'media' => ['layout' => 'text', 'accent' => TypeColors::hex('media'), 'eyebrow' => 'Books', 'title' => 'I read Atomic Habits', 'date' => 'Fri 3 Jan 2025', 'meta' => 'James Clear'],
            'appearance' => ['layout' => 'text', 'accent' => TypeColors::hex('appearance'), 'eyebrow' => 'Appearance', 'title' => 'Building a Lifelog in Laravel', 'date' => 'Thu 6 Feb 2026', 'meta' => 'Laracon EU'],
            'podcast' => ['layout' => 'text', 'accent' => TypeColors::hex('podcast'), 'eyebrow' => 'This Week With', 'title' => 'Season 7, Episode 249', 'date' => 'Thu 19 Jun 2026', 'meta' => '21 min, Taylor & Gordon'],
            'article' => ['layout' => 'text', 'accent' => TypeColors::hex('article'), 'eyebrow' => 'Article', 'title' => 'Why I track absolutely everything', 'date' => 'Mon 12 May 2025', 'meta' => '6 min read'],
            'note' => ['layout' => 'text', 'accent' => TypeColors::hex('note'), 'eyebrow' => 'Note', 'title' => 'A quick thought on building in public', 'date' => 'Tue 24 Jun 2026', 'meta' => null],
            'project' => ['layout' => 'text', 'accent' => TypeColors::hex('project'), 'eyebrow' => 'Project', 'title' => 'taylordrayson.com', 'date' => null, 'meta' => 'Laravel, Inertia, Vue'],
            'event' => ['layout' => 'text', 'accent' => TypeColors::hex('event'), 'eyebrow' => 'Event', 'title' => 'Laracon EU', 'date' => 'Tue 28 Jan 2026', 'meta' => 'Amsterdam'],
            'sleep' => ['layout' => 'text', 'accent' => TypeColors::hex('sleep'), 'eyebrow' => 'Sleep', 'title' => 'I slept 7h 32m', 'date' => 'Wed 25 Jun 2026', 'stages' => $this->entryOgData->sleepStages([
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
     * A public image embedded as a data URI, so Browsershot needs no network fetch.
     */
    public function dataUri(string $file, string $mime): string
    {
        $path = public_path($file);

        return is_file($path) ? "data:{$mime};base64,".base64_encode((string) file_get_contents($path)) : '';
    }

    /**
     * A safe 6-digit hex colour (without the hash), or the brand default.
     */
    public function accent(?string $value): string
    {
        $hex = ltrim((string) $value, '#');

        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? strtolower($hex) : BuildEntryOgData::ACCENT_DEFAULT;
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
            $label = BuildEntryOgData::TYPE_EYEBROWS[$type] ?? $type;
            $accentToken = $type === 'calorie' ? 'food' : $type;

            $cards[] = [
                'label' => $label,
                'url' => $this->ogUrl(OgMeta::archive($type, $label, $label, $accentToken, false, Str::lower(Str::singular($label)), 0)),
            ];
        }

        return $cards;
    }

    /**
     * Build the /og.png URL that renders a given OgMeta payload's card.
     *
     * Carries the same design token the real pages emit, so opening a gallery
     * URL after a redesign is not answered from the browser's copy of the card
     * it replaced.
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
            'v' => OgRenderer::generation(),
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

        foreach (BuildEntryOgData::TYPE_EYEBROWS as $type => $label) {
            $model = TypeRegistry::find($type)['model'] ?? null;

            if ($model === null) {
                continue;
            }

            $entry = TimelineEntry::query()
                ->where('timelineable_type', $model)
                ->latest('occurred_at')
                ->first();

            if ($entry !== null) {
                $cards[] = ['label' => "Entry: {$label}", 'url' => OgMeta::entryCardUrl($entry)];
            }
        }

        return $cards;
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
}
