<?php

namespace App\Queries;

use App\Enums\SubjectKind;
use App\Models\Page;
use App\Models\Series;
use App\Models\Subject;
use App\Models\Tag;
use App\Models\TimelineEntry;
use App\Models\Trip;
use App\Stories\StoryRegistry;
use App\Support\SqlDate;
use App\Timeline\TypeRegistry;
use Illuminate\Support\Carbon;

/**
 * The URL lists behind the sitemap.
 *
 * Entries are split a year per file rather than paged: the split is stable, so
 * a crawler that has seen 2019 need not fetch it again, and each file's
 * lastmod is a real answer to whether anything in that year changed.
 *
 * @phpstan-type SitemapUrl array{loc: string, lastmod: ?string}
 */
final class SitemapUrls
{
    public function __construct(private readonly StoryRegistry $stories) {}

    /**
     * Every year holding at least one entry, newest first.
     *
     * @return list<int>
     */
    public function years(): array
    {
        return TimelineEntry::query()
            ->selectRaw(SqlDate::year('occurred_at').' as year')
            ->groupBy('year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->all();
    }

    /**
     * When anything in a year last changed, for that file's index entry.
     */
    public function yearLastModified(int $year): ?string
    {
        $stamp = TimelineEntry::query()
            ->whereYear('occurred_at', $year)
            ->max('updated_at');

        return $stamp ? Carbon::parse($stamp)->toAtomString() : null;
    }

    /**
     * One year's entries, plus the dated timeline pages that list them.
     *
     * @return list<SitemapUrl>
     */
    public function year(int $year): array
    {
        $entries = TimelineEntry::query()
            ->select('url_slug', 'occurred_at', 'updated_at')
            ->whereYear('occurred_at', $year)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (TimelineEntry $entry): array => [
                'loc' => $entry->occurred_at->format('/Y/m/d').'/'.$entry->url_slug,
                'lastmod' => $entry->updated_at?->toAtomString(),
            ])
            ->all();

        return [...$this->dateUrls($year), ...$entries];
    }

    /**
     * The year page and each month and day inside it that has an entry. Built
     * from one grouped query rather than a date loop, so empty days are never
     * offered to a crawler.
     *
     * @return list<SitemapUrl>
     */
    private function dateUrls(int $year): array
    {
        $days = TimelineEntry::query()
            ->selectRaw(SqlDate::date('occurred_at').' as day, MAX(updated_at) as changed')
            ->whereYear('occurred_at', $year)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        if ($days->isEmpty()) {
            return [];
        }

        $months = $days->groupBy(fn (object $row): string => substr((string) $row->day, 0, 7));

        $urls = [['loc' => "/{$year}", 'lastmod' => $this->yearLastModified($year)]];

        foreach ($months as $month => $rows) {
            $urls[] = [
                'loc' => '/'.str_replace('-', '/', (string) $month),
                'lastmod' => Carbon::parse($rows->max('changed'))->toAtomString(),
            ];
        }

        foreach ($days as $row) {
            $urls[] = [
                'loc' => '/'.str_replace('-', '/', (string) $row->day),
                'lastmod' => Carbon::parse($row->changed)->toAtomString(),
            ];
        }

        return $urls;
    }

    /**
     * Everything that is not a dated entry: the fixed pages, every archive and
     * its taxonomy values, stats, stories, tags, trips, shows and CMS pages.
     *
     * @return list<SitemapUrl>
     */
    public function pages(): array
    {
        return [
            ...$this->fixed(),
            ...$this->archives(),
            ...$this->stories(),
            ...$this->records(),
        ];
    }

    /**
     * @return list<SitemapUrl>
     */
    private function fixed(): array
    {
        $latest = TimelineEntry::query()->max('updated_at');
        $lastmod = $latest ? Carbon::parse($latest)->toAtomString() : null;

        // The home page and the two live views change whenever anything is
        // logged; the rest are structural and carry no lastmod of their own.
        $moving = ['/', '/now', '/on-this-day', '/photos'];
        $static = [
            '/more', '/feeds', '/tags', '/trips', '/media/tv', '/flights/map', '/leaderboard',
            '/life', ...array_map(fn (SubjectKind $kind): string => '/life/'.$kind->segment(), SubjectKind::cases()),
        ];

        return [
            ...array_map(fn (string $loc): array => ['loc' => $loc, 'lastmod' => $lastmod], $moving),
            ...array_map(fn (string $loc): array => ['loc' => $loc, 'lastmod' => null], $static),
        ];
    }

    /**
     * Each type's archive, its stats page, and one URL per taxonomy value.
     *
     * @return list<SitemapUrl>
     */
    private function archives(): array
    {
        $urls = [];

        foreach (TypeRegistry::all() as $definition) {
            $urls[] = ['loc' => '/'.$definition['slug'], 'lastmod' => null];
            $urls[] = ['loc' => '/stats/'.$definition['slug'], 'lastmod' => null];

            if ($definition['taxonomy'] === null) {
                continue;
            }

            foreach ($definition['taxonomy']['values']() as $value) {
                $urls[] = ['loc' => '/'.$definition['taxonomy']['base'].'/'.$value['value'], 'lastmod' => null];
            }
        }

        return $urls;
    }

    /**
     * @return list<SitemapUrl>
     */
    private function stories(): array
    {
        return [
            ['loc' => '/stories', 'lastmod' => null],
            ...array_map(
                fn ($story): array => ['loc' => '/stories/'.$story->slug(), 'lastmod' => null],
                $this->stories->all(),
            ),
        ];
    }

    /**
     * Tags, trips, shows and published CMS pages.
     *
     * @return list<SitemapUrl>
     */
    private function records(): array
    {
        $tags = Tag::query()->orderBy('slug')->get(['slug'])
            ->map(fn (Tag $tag): array => ['loc' => $tag->url(), 'lastmod' => null]);

        $trips = Trip::query()->orderBy('slug')->get(['slug'])
            ->map(fn (Trip $trip): array => ['loc' => $trip->url(), 'lastmod' => null]);

        $series = Series::query()->orderBy('slug')->get(['slug'])
            ->map(fn (Series $show): array => ['loc' => $show->url(), 'lastmod' => null]);

        $pages = Page::query()->where('published', true)->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->map(fn (Page $page): array => [
                'loc' => $page->url(),
                'lastmod' => $page->updated_at?->toAtomString(),
            ]);

        // A subject carries a lastmod, unlike the others above: a bio genuinely
        // changes, where a tag or trip's URL is stable and its content derived.
        $subjects = Subject::query()->orderBy('kind')->orderBy('slug')
            ->get(['kind', 'slug', 'updated_at'])
            ->map(fn (Subject $subject): array => [
                'loc' => $subject->url(),
                'lastmod' => $subject->updated_at?->toAtomString(),
            ]);

        return [...$tags, ...$trips, ...$series, ...$pages, ...$subjects];
    }
}
