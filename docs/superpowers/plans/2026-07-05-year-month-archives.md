# Year & Month Archives (Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hardcoded Year page with real data (numbers, full-year GitHub-style heatmap with month-label links, paginated everything-timeline) and extend the Month page (photos strip, paginated timeline tail).

**Architecture:** All server work stays in `TimelineController`: a shared `periodStats()` SQL-aggregate helper feeds both year and month stat rows; the year heatmap is one spine group-by; the timeline tails reuse the existing day-paginated `groupsForDates` pattern (extended with an ascending mode) and ship as Inertia deferred props. The existing `Heatmap.vue` gains a real-data year mode (clickable month labels, day links) while keeping its procedural fallback for /now.

**Tech Stack:** Laravel 13, Inertia v3 (deferred props, `<Deferred>`), Vue 3, Pest 4, Tailwind v4 tokens.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-05-year-month-archives-design.md` (Phase 1 only — NO superlatives, NO travel section).
- Every section/stat is self-hiding when empty; a sparse year renders fewer things, never zero-states.
- Timeline tails contain EVERYTHING (no curation), day-grouped, **chronological ascending**, day-paginated via `?page=`; stats/heatmap always cover the full period regardless of page.
- Heatmap: GitHub contribution style (existing visual language), month labels link to `/{year}/{MM}`, day cells link to `/{year}/{MM}/{DD}`; count buckets 0 / 1–2 / 3–5 / 6–9 / 10+.
- No arbitrary Tailwind bracket values; icons via `Icon.vue`; Vue computeds/functions get comments.
- `vendor/bin/pint --dirty --format agent` after PHP edits; run affected Pest tests per task (`php artisan test --compact --filter=...`).
- Do NOT touch `/now`'s use of Heatmap beyond keeping it rendering (procedural fallback stays).

---

### Task 1: `periodStats` aggregates + year payload (stats, heatmap, count)

**Files:**
- Modify: `app/Http/Controllers/TimelineController.php` (`year()`, `month()`, replace `monthStats()` with `periodStats()`)
- Test: `tests/Feature/YearArchiveTest.php` (create)

**Interfaces:**
- Consumes: `TimelineEntry` spine, `Distance::km()`, existing `OgMeta::year/month`.
- Produces (Year props): `year:int`, `og`, `entriesCount:int`, `stats:array<{label,value?,unit?,seconds?}>`, `heatmap:array<string yyyy-mm-dd, int count>`. `month()` keeps its existing props but `stats` now comes from `periodStats()`.
- Produces (method): `private function periodStats(Carbon $start, Carbon $end): array` — the superset stat row used by both year and month.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/YearArchiveTest.php

use App\Models\Activity;
use App\Models\Article;
use App\Models\Flight;
use App\Models\Note;
use App\Models\Sleep;

use function Pest\Laravel\get;

it('serves real year numbers, entry count and heatmap', function () {
    Activity::factory()->create(['occurred_at' => '2025-03-10 09:00:00', 'distance' => 5000]);
    Activity::factory()->create(['occurred_at' => '2025-03-10 18:00:00', 'distance' => 7000]);
    Sleep::factory()->create(['occurred_at' => '2025-03-11 00:00:00', 'duration' => 8 * 3600]);
    Flight::factory()->create(['occurred_at' => '2025-06-01 10:00:00']);
    Article::factory()->create(['occurred_at' => '2025-07-01 12:00:00', 'published' => true]);
    Note::factory()->create(['occurred_at' => '2025-07-02 12:00:00']);

    get('/2025')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Year')
            ->where('entriesCount', 6)
            ->where('stats', fn ($stats) => collect($stats)->pluck('label')->contains('Activities'))
            ->where('stats', fn ($stats) => collect($stats)->pluck('label')->contains('Written'))
            ->where('heatmap.2025-03-10', 2)
            ->where('heatmap.2025-06-01', 1));
});

it('excludes other years from the aggregates', function () {
    Activity::factory()->create(['occurred_at' => '2024-12-31 09:00:00']);

    get('/2025')->assertInertia(fn ($page) => $page
        ->where('entriesCount', 0)
        ->where('stats', [])
        ->where('heatmap', []));
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=YearArchiveTest`
Expected: FAIL — `entriesCount` prop missing (Year currently only receives `year` + `og`).

- [ ] **Step 3: Implement `periodStats()` and the year payload**

In `TimelineController`, replace `monthStats(Collection $entries, Carbon $start, Carbon $end)` with a SQL-aggregate version and wire `year()`:

```php
use App\Models\Article;
use App\Models\Checkin;
use App\Models\Media;
use App\Models\Note;

public function year(int $year): Response
{
    $start = Carbon::create($year, 1, 1)->startOfDay();
    $end = (clone $start)->endOfYear()->endOfDay();

    return Inertia::render('Year', [
        'year' => $year,
        'og' => OgMeta::year($year),
        'entriesCount' => TimelineEntry::whereBetween('occurred_at', [$start, $end])->count(),
        'stats' => $this->periodStats($start, $end),
        'heatmap' => $this->heatmapDays($start, $end),
    ]);
}

/**
 * Entries per day for the contribution heatmap, keyed yyyy-mm-dd.
 *
 * @return array<string, int>
 */
private function heatmapDays(Carbon $start, Carbon $end): array
{
    return TimelineEntry::query()
        ->toBase()
        ->selectRaw('DATE(occurred_at) as date, COUNT(*) as total')
        ->whereBetween('occurred_at', [$start, $end])
        ->groupBy('date')
        ->pluck('total', 'date')
        ->map(fn ($total): int => (int) $total)
        ->all();
}

/**
 * Roll-up stat row shared by the year and month pages. Every stat
 * self-hides at zero, so sparse periods just show fewer numbers.
 *
 * @return array<int, array<string, mixed>>
 */
private function periodStats(Carbon $start, Carbon $end): array
{
    $between = fn ($query) => $query->whereBetween('occurred_at', [$start, $end]);

    $stats = [];

    $activities = $between(Activity::query())->count();

    if ($activities > 0) {
        $stats[] = ['label' => 'Activities', 'value' => number_format($activities)];

        $distanceKm = Distance::km((int) $between(Activity::query())->sum('distance')) ?? 0.0;

        if ($distanceKm > 0) {
            $stats[] = ['label' => 'Distance', 'value' => number_format($distanceKm), 'unit' => 'km'];
        }
    }

    $avgSleep = (int) round($between(Sleep::query())->avg('duration') ?? 0);

    if ($avgSleep > 0) {
        $stats[] = ['label' => 'Avg sleep', 'seconds' => $avgSleep];
    }

    $foodDays = (int) $between(Calorie::query())->toBase()->selectRaw('COUNT(DISTINCT DATE(occurred_at)) as days')->value('days');

    if ($foodDays > 0) {
        $stats[] = ['label' => 'Days of food', 'value' => number_format($foodDays)];
    }

    $films = $between(Media::query())->whereIn('type', ['film', 'show'])->count();

    if ($films > 0) {
        $stats[] = ['label' => 'Watched', 'value' => number_format($films)];
    }

    $flights = $between(Flight::query())->count();

    if ($flights > 0) {
        $stats[] = ['label' => 'Flights', 'value' => number_format($flights)];
    }

    $written = $between(Article::query())->where('published', true)->count() + $between(Note::query())->count();

    if ($written > 0) {
        $stats[] = ['label' => 'Written', 'value' => number_format($written)];
    }

    $places = $between(Checkin::query())->count();

    if ($places > 0) {
        $stats[] = ['label' => 'Places', 'value' => number_format($places)];
    }

    return $stats;
}
```

In `month()`, replace `'stats' => $this->monthStats($entries, $start, $end)` with `'stats' => $this->periodStats($start, $end)` and delete `monthStats()` (its `Calories` stat is superseded by `Days of food`; keep the kcal stat by adding it to `periodStats` if `CalendarViewsTest` depends on it — check the test, it only asserts `Activities` is present).

- [ ] **Step 4: Run the tests**

Run: `php artisan test --compact --filter="YearArchiveTest|CalendarViewsTest"`
Expected: PASS (adjust nothing in existing tests unless a label they assert was renamed — `Activities` is kept verbatim).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/TimelineController.php tests/Feature/YearArchiveTest.php
git commit -m "feat: real year aggregates - periodStats, heatmap days, entry count"
```

---

### Task 2: Paginated ascending deferred tails (year + month) and month photos payload

**Files:**
- Modify: `app/Http/Controllers/TimelineController.php` (`year()`, `month()`, `groupsForDates()`)
- Test: `tests/Feature/YearArchiveTest.php` (extend)

**Interfaces:**
- Consumes: Task 1's `year()` payload; `BuildTimelineFeed::groupByDay` (preserves iteration order); `HasAttachments::galleryPhotos()`.
- Produces: both pages gain `groups` (**deferred** prop: `array<{label,date,href,items}>` ascending), `currentPage:int`, `lastPage:int`. Month additionally gains `photos: array<{src,srcset,full}>` (max 12).
- `groupsForDates(string $from, string $to, bool $ascending = false)` — existing callers keep newest-first default.

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/YearArchiveTest.php`)

```php
use Inertia\Support\Header;

/** Follow up an Inertia page with a partial reload so deferred props resolve. */
function deferredProps(string $url, string $component, array $props): \Illuminate\Testing\TestResponse
{
    return test()->get($url, [
        Header::INERTIA => 'true',
        Header::PARTIAL_COMPONENT => $component,
        Header::PARTIAL_ONLY => implode(',', $props),
    ]);
}

it('serves the year timeline tail ascending, day-paginated and deferred', function () {
    foreach (range(1, 12) as $day) {
        Note::factory()->create(['occurred_at' => sprintf('2025-05-%02d 10:00:00', $day)]);
    }

    // Initial load: deferred prop absent, pagination metadata present.
    get('/2025')->assertInertia(fn ($page) => $page
        ->missing('groups')
        ->where('currentPage', 1)
        ->where('lastPage', 2));

    // Partial reload resolves the tail: page 1 = oldest 10 days, ascending.
    deferredProps('/2025', 'Year', ['groups'])
        ->assertInertia(fn ($page) => $page
            ->has('groups', 10)
            ->where('groups.0.date', '2025-05-01')
            ->where('groups.9.date', '2025-05-10'));

    deferredProps('/2025?page=2', 'Year', ['groups'])
        ->assertInertia(fn ($page) => $page
            ->has('groups', 2)
            ->where('groups.0.date', '2025-05-11'));
});

it('serves the month tail and photos strip', function () {
    Storage::fake('public');
    $note = Note::factory()->create(['occurred_at' => '2025-05-03 10:00:00']);
    $note->addMediaFromString(yearPhotoJpegBytes())->usingFileName('note.jpg')->toMediaCollection('photos');

    get('/2025/05')->assertInertia(fn ($page) => $page
        ->component('Month')
        ->missing('groups')
        ->has('photos', 1)
        ->has('photos.0.src'));

    deferredProps('/2025/05', 'Month', ['groups'])
        ->assertInertia(fn ($page) => $page
            ->has('groups', 1)
            ->where('groups.0.date', '2025-05-03'));
});
```

Add the image helper at the top of the file (mirrors `ArticleCoverTest`):

```php
function yearPhotoJpegBytes(): string
{
    $image = imagecreatetruecolor(640, 480);
    imagefilledrectangle($image, 0, 0, 639, 479, imagecolorallocate($image, 90, 120, 40));
    ob_start();
    imagejpeg($image, null, 80);

    return (string) ob_get_clean();
}
```

Also add `use Illuminate\Support\Facades\Storage;` to the imports.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=YearArchiveTest`
Expected: the two new tests FAIL (`groups`/`photos` props missing). (If `Inertia\Support\Header::PARTIAL_ONLY` does not exist in this inertia-laravel version, use the literal header names: `X-Inertia`, `X-Inertia-Partial-Component`, `X-Inertia-Partial-Data`.)

- [ ] **Step 3: Implement**

`groupsForDates` gains an ascending mode (keep default behaviour for `index()`/`show()` callers):

```php
private function groupsForDates(string $newest, string $oldest, bool $ascending = false): array
{
    $entries = TimelineEntry::query()
        ->withCardRelations()
        ->whereDate('occurred_at', '<=', $newest)
        ->whereDate('occurred_at', '>=', $oldest)
        ->orderBy('occurred_at', $ascending ? 'asc' : 'desc')
        ->get();

    return $this->feed->groupByDay($entries);
}
```

Add a shared tail builder and wire both pages:

```php
/**
 * Day-paginated, chronological timeline tail for a period. Returns the
 * pagination metadata immediately and the (expensive) hydrated groups as
 * a deferred closure, so the archive's stats paint before its feed.
 *
 * @return array{groups: \Inertia\DeferProp, currentPage: int, lastPage: int}
 */
private function periodTail(Carbon $start, Carbon $end): array
{
    $days = TimelineEntry::query()
        ->toBase()
        ->selectRaw('DATE(occurred_at) as date')
        ->whereBetween('occurred_at', [$start, $end])
        ->groupBy('date')
        ->orderBy('date')
        ->paginate(self::DAYS_PER_PAGE);

    $dates = collect($days->items())->pluck('date');

    return [
        'groups' => Inertia::defer(fn (): array => $dates->isEmpty()
            ? []
            : $this->groupsForDates($dates->last(), $dates->first(), true)),
        'currentPage' => $days->currentPage(),
        'lastPage' => $days->lastPage(),
    ];
}
```

In `year()` spread it into the props: `...$this->periodTail($start, $end)`. Same in `month()`, plus the photos strip built from the already-hydrated month entries:

```php
'photos' => $entries
    ->map(fn (TimelineEntry $entry) => $entry->timelineable)
    ->filter(fn ($model): bool => method_exists($model, 'galleryPhotos'))
    ->flatMap(fn ($model): array => $model->galleryPhotos())
    ->take(12)
    ->values()
    ->all(),
```

(`groupByDay` groups in iteration order, and each group's `date`/`label`/`href` already exist — no changes needed there. Ascending input yields ascending groups and ascending items.)

- [ ] **Step 4: Run the tests**

Run: `php artisan test --compact --filter="YearArchiveTest|CalendarViewsTest|FeedTest|TimelineCardTimesTest"`
Expected: PASS — including the untouched newest-first home feed tests.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/TimelineController.php tests/Feature/YearArchiveTest.php
git commit -m "feat: paginated ascending deferred timeline tails + month photos payload"
```

---

### Task 3: Heatmap real-data year mode with month links + Year.vue rebuild

**Files:**
- Modify: `resources/js/Components/Stats/Heatmap.vue`
- Rewrite: `resources/js/Pages/Year.vue`
- Verify: `npm run build`, then screenshot `/2025` (or a seeded year) — no Pest coverage for Vue internals; server payloads are covered by Tasks 1–2.

**Interfaces:**
- Consumes: Year props from Tasks 1–2 (`entriesCount`, `stats`, `heatmap`, deferred `groups`, `currentPage`, `lastPage`).
- Produces: `Heatmap` new optional props `{ days: Object (yyyy-mm-dd → count), year: Number }`. With them it renders the real year (day-linked cells, month-label links); without them the existing procedural /now rendering is unchanged.

- [ ] **Step 1: Extend Heatmap.vue**

Replace the component with (procedural fallback preserved):

```vue
<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    // Real data mode: entries-per-day keyed yyyy-mm-dd, for `year`.
    days: { type: Object, default: null },
    year: { type: Number, default: null },
});

const ramp = ['var(--color-neutral-25)', 'var(--color-heat-1)', 'var(--color-heat-2)', 'var(--color-heat-3)', 'var(--color-heat-4)'];

// GitHub-style buckets: 0, 1-2, 3-5, 6-9, 10+.
function bucket(count) {
    if (count <= 0) return 0;
    if (count <= 2) return 1;
    if (count <= 5) return 2;
    if (count <= 9) return 3;
    return 4;
}

const pad = (value) => String(value).padStart(2, '0');

// Real-data cells: leading nulls pad the first week so columns are true
// Mon-Sun weeks; each cell carries its date, count and day-page href.
const yearCells = computed(() => {
    if (!props.days || !props.year) return null;

    const cells = [];
    const first = new Date(props.year, 0, 1);
    const offset = (first.getDay() + 6) % 7;

    for (let i = 0; i < offset; i++) cells.push(null);

    const date = new Date(props.year, 0, 1);
    while (date.getFullYear() === props.year) {
        const key = `${props.year}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        const count = props.days[key] ?? 0;
        cells.push({
            key,
            href: `/${props.year}/${pad(date.getMonth() + 1)}/${pad(date.getDate())}`,
            title: `${date.getDate()} ${date.toLocaleDateString('en-GB', { month: 'short' })} · ${count} ${count === 1 ? 'entry' : 'entries'}`,
            level: bucket(count),
        });
        date.setDate(date.getDate() + 1);
    }

    return cells;
});

// Month labels positioned by the week column their 1st falls into.
const monthLabels = computed(() => {
    if (!props.year) return [];

    const first = new Date(props.year, 0, 1);
    const offset = (first.getDay() + 6) % 7;

    return Array.from({ length: 12 }, (_, month) => {
        const start = new Date(props.year, month, 1);
        const dayOfYear = Math.round((start - first) / 86400000);
        return {
            label: start.toLocaleDateString('en-GB', { month: 'short' }),
            href: `/${props.year}/${pad(month + 1)}`,
            column: Math.floor((offset + dayOfYear) / 7) + 1,
        };
    });
});

// Fallback: the original procedural texture for hosts without data (/now).
const fallbackCells = Array.from({ length: 364 }, (_, i) => {
    const raw = (Math.sin((i + 1) * 43.13) * 4313.13) % 1;
    const value = raw < 0 ? raw + 1 : raw;

    return value < 0.18 ? 0 : value < 0.42 ? 1 : value < 0.68 ? 2 : value < 0.88 ? 3 : 4;
});

function color(level) {
    return ramp[level];
}
</script>

<template>
    <div>
        <!-- Month labels double as year → month navigation. -->
        <div v-if="yearCells" class="heatmap-months mb-1 text-xs text-neutral-500">
            <Link
                v-for="month in monthLabels"
                :key="month.href"
                :href="month.href"
                :style="{ gridColumnStart: month.column }"
                class="rounded-sm underline-offset-2 transition-colors hover:text-accent-500 hover:underline focus-visible:text-accent-500 focus-visible:underline focus-visible:outline-none"
            >{{ month.label }}</Link>
        </div>

        <div v-if="yearCells" class="heatmap-grid">
            <template v-for="(cell, index) in yearCells" :key="cell ? cell.key : `pad-${index}`">
                <Link
                    v-if="cell"
                    :href="cell.href"
                    :title="cell.title"
                    :aria-label="cell.title"
                    class="rounded focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-accent-500"
                    :style="{ background: color(cell.level) }"
                />
                <span v-else />
            </template>
        </div>
        <div v-else class="heatmap-grid">
            <span v-for="(level, index) in fallbackCells" :key="index" class="rounded" :style="{ background: color(level) }" />
        </div>

        <div class="mt-3 flex items-center gap-1.5 text-xs text-neutral-500">
            Quieter
            <span v-for="(swatch, index) in ramp" :key="index" class="inline-block size-3 rounded" :style="{ background: swatch }" />
            Busier
        </div>
    </div>
</template>

<style scoped>
/* Cells flow column-major into 7 weekday rows → ~53 week columns.
   1fr columns stretch the grid to the container width; aspect-ratio keeps cells square. */
.heatmap-grid {
    display: grid;
    grid-auto-flow: column;
    grid-template-rows: repeat(7, 1fr);
    grid-auto-columns: 1fr;
    gap: 2px;
}

.heatmap-grid > * {
    aspect-ratio: 1;
}

/* Same column geometry as the grid so labels sit over their month's first week. */
.heatmap-months {
    display: grid;
    grid-auto-columns: 1fr;
    grid-auto-flow: column;
    grid-template-columns: repeat(53, 1fr);
}
</style>
```

- [ ] **Step 2: Rebuild Year.vue**

Replace the whole file:

```vue
<script setup>
import { computed } from 'vue';
import { setLayoutProps, Deferred } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import ViewHeader from '../Components/Layout/ViewHeader.vue';
import StatGrid from '../Components/Stats/StatGrid.vue';
import SectionHead from '../Components/Ui/SectionHead.vue';
import Heatmap from '../Components/Stats/Heatmap.vue';
import Pagination from '../Components/Ui/Pagination.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import FutureNote from '../Components/Timeline/FutureNote.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    year: { type: Number, required: true },
    og: { type: Object, default: () => ({}) },
    entriesCount: { type: Number, default: 0 },
    stats: { type: Array, default: () => [] },
    heatmap: { type: Object, default: () => ({}) },
    groups: { type: Array, default: null }, // deferred
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
});

const isFuture = computed(() => props.year > new Date().getFullYear());
const subtitle = computed(() => `${props.entriesCount.toLocaleString('en-GB')} ${props.entriesCount === 1 ? 'entry' : 'entries'} logged`);

setLayoutProps({
    breadcrumb: [{ label: String(props.year) }],
});
</script>

<template>
    <AppHead :og="og" />

    <FutureNote v-if="isFuture" unit="year" />

    <template v-else>
        <!-- Not a data-type page: plain H1, no eyebrow. -->
        <ViewHeader
            :title="String(year)"
            :subtitle="subtitle"
            :prev="{ label: String(year - 1), href: `/${year - 1}` }"
            :next="{ label: String(year + 1), href: `/${year + 1}` }"
        />

        <StatGrid v-if="stats.length" :stats="stats" size="lg" class="mt-8" />

        <section v-if="entriesCount">
            <SectionHead title="The year" :meta="`${Object.keys(heatmap).length} days logged`" />
            <Heatmap :days="heatmap" :year="year" />
        </section>

        <section v-if="entriesCount">
            <SectionHead title="Everything" meta="oldest first" />
            <Deferred data="groups">
                <template #fallback>
                    <!-- Pulsing skeleton while the tail loads. -->
                    <div class="space-y-6">
                        <div v-for="i in 3" :key="i" class="animate-pulse space-y-3">
                            <div class="h-6 w-48 rounded-md bg-neutral-25" />
                            <div class="h-24 rounded-lg bg-neutral-25" />
                        </div>
                    </div>
                </template>

                <div class="flex flex-col gap-14">
                    <DateGroup
                        v-for="group in groups"
                        :key="group.date"
                        :label="group.label"
                        :date="group.date"
                        :href="group.href"
                        :items="group.items"
                    />
                </div>
            </Deferred>

            <Pagination
                v-if="lastPage > 1"
                class="mt-14"
                :current-page="currentPage"
                :last-page="lastPage"
                :prev-url="currentPage > 1 ? `/${year}?page=${currentPage - 1}` : null"
                :next-url="currentPage < lastPage ? `/${year}?page=${currentPage + 1}` : null"
            />
        </section>

        <p v-if="!entriesCount" class="mt-10 text-meta text-neutral-500">Nothing logged in {{ year }}.</p>
    </template>
</template>
```

(Check `Pagination.vue`'s exact prop names before wiring — `prevUrl`/`nextUrl` per its current API; also check how `Timeline.vue` builds pagination URLs and mirror it.)

- [ ] **Step 3: Build + verify**

Run: `npm run build` — expect clean.
Screenshot `http://taylordrayson.test/2025` (dev DB has 2017–2026 data): stats row real, heatmap coloured with linked month labels, tail loads after skeleton, pagination present. Also screenshot `/now` to confirm the fallback heatmap still renders.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Stats/Heatmap.vue resources/js/Pages/Year.vue
git commit -m "feat: real Year page - stats, linked contribution heatmap, deferred paginated tail"
```

---

### Task 4: Month.vue photos strip + timeline tail

**Files:**
- Modify: `resources/js/Pages/Month.vue`
- Verify: `npm run build` + screenshots.

**Interfaces:**
- Consumes: Month props from Task 2 (`photos`, deferred `groups`, `currentPage`, `lastPage`) plus existing (`days`, `stats`, `entriesCount`).

- [ ] **Step 1: Extend Month.vue**

Add to the props block:

```js
photos: { type: Array, default: () => [] },
groups: { type: Array, default: null }, // deferred
currentPage: { type: Number, default: 1 },
lastPage: { type: Number, default: 1 },
```

Add imports: `Deferred` from `@inertiajs/vue3`, `SectionHead`, `DateGroup`, `Pagination`, `Lightbox` (`../Components/Overlays/Lightbox.vue`), `ZoomButton`, and `ref`.

After `<CalendarMonth ... />` in the template append:

```vue
<section v-if="photos.length">
    <SectionHead title="Photos" :meta="`${photos.length}${photos.length === 12 ? '+' : ''} this month`" />
    <div class="grid grid-cols-3 gap-2.5 sm:grid-cols-6">
        <button
            v-for="(photo, index) in photos"
            :key="index"
            type="button"
            :aria-label="`View photo ${index + 1}`"
            class="group/zoom relative aspect-square overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 transition-opacity hover:opacity-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            @click="lightboxIndex = index"
        >
            <img :src="photo.src" :srcset="photo.srcset || undefined" sizes="(min-width: 768px) 16vw, 33vw" alt="" class="size-full object-cover">
            <span class="pointer-events-none absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100">
                <ZoomButton />
            </span>
        </button>
    </div>
    <Lightbox v-model:index="lightboxIndex" :photos="photos" />
</section>

<section>
    <SectionHead title="Everything" meta="oldest first" />
    <Deferred data="groups">
        <template #fallback>
            <div class="space-y-6">
                <div v-for="i in 3" :key="i" class="animate-pulse space-y-3">
                    <div class="h-6 w-48 rounded-md bg-neutral-25" />
                    <div class="h-24 rounded-lg bg-neutral-25" />
                </div>
            </div>
        </template>

        <div class="flex flex-col gap-14">
            <DateGroup
                v-for="group in groups"
                :key="group.date"
                :label="group.label"
                :date="group.date"
                :href="group.href"
                :items="group.items"
            />
        </div>
    </Deferred>

    <Pagination
        v-if="lastPage > 1"
        class="mt-14"
        :current-page="currentPage"
        :last-page="lastPage"
        :prev-url="currentPage > 1 ? `/${year}/${pad(month)}?page=${currentPage - 1}` : null"
        :next-url="currentPage < lastPage ? `/${year}/${pad(month)}?page=${currentPage + 1}` : null"
    />
</section>
```

And in the script: `const lightboxIndex = ref(null);` with a comment (`// Which photo the lightbox is showing (null = closed).`).

- [ ] **Step 2: Build + verify**

Run: `npm run build` — clean. Screenshot `http://taylordrayson.test/2026/06` (has real activities/notes) and one photo-bearing month (`/2026/07`): calendar + photos strip + tail render; a month with >10 active days paginates.

- [ ] **Step 3: Run the whole suite**

Run: `php artisan test --compact`
Expected: everything green.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Month.vue
git commit -m "feat: month archive - photos strip and deferred paginated timeline tail"
```
