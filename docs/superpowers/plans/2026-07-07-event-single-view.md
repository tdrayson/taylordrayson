# Event Single-View & Multi-Day Display Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give imported events a rich single-view (location map, cover + gallery, address→Google link) and correct multi-day behaviour (shown on every covered day, counted once).

**Architecture:** Events mirror activities. Multi-day is a *query* concern (range-aware day/on-this-day via a mirrored `timeline_entries.ends_at`) kept separate from a *row* concern (one mirror row per event → counts unchanged). A static Mapbox pin PNG is the card fallback; a generic MapLibre/OpenFreeMap `LocationMap.vue` is the interactive entry-view map.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 4, Inertia v3 + Vue 3, Tailwind v4, MapLibre GL + OpenFreeMap (interactive), Mapbox Static Images API (static).

## Global Constraints

- PHP 8.4; explicit return types on all methods; curly braces on all control structures; constructor property promotion.
- No `env()` outside config files. Read Mapbox token via `config('services.mapbox.token')`.
- Run `vendor/bin/pint --dirty --format agent` after editing PHP, before each commit.
- Tests are Pest; Feature tests use `RefreshDatabase`: `php artisan test --compact --filter=<name>`.
- `occurred_at` is local wall-clock; never shift stored times. Stored `type` values are kebab-case.
- No em dashes in any generated copy or UI text.
- Vue: comment computeds and non-obvious logic; never use arbitrary Tailwind bracket values; single root element per component.
- Database is SQLite; `strftime`/`DATE`/`COALESCE` are acceptable in raw expressions.
- Do not change dependencies.

---

### Task 1: Mirror `ends_at` onto `timeline_entries` + range scopes

**Files:**
- Create: `database/migrations/2026_07_07_000000_add_ends_at_to_timeline_entries.php`
- Modify: `app/Observers/TimelineEntryObserver.php` (the `updateOrCreate` values array in `saved`)
- Modify: `app/Models/TimelineEntry.php` (add two query scopes)
- Test: `tests/Feature/TimelineEntryRangeTest.php`

**Interfaces:**
- Produces: `timeline_entries.ends_at` (nullable timestamp); `TimelineEntry::scopeCoveringDate(Builder, string $date): Builder`; `TimelineEntry::scopeCoveringAnniversary(Builder, string $monthDay): Builder`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TimelineEntryRangeTest.php`:

```php
<?php

use App\Models\Event;
use App\Models\TimelineEntry;

it('mirrors ends_at from a multi-day event onto its timeline entry', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => '2022-06-04 18:00:00',
    ]);

    expect($event->timelineEntry->ends_at->toDateString())->toBe('2022-06-04');
});

it('leaves ends_at null for single-day entries', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => null,
    ]);

    expect($event->timelineEntry->ends_at)->toBeNull();
});

it('coveringDate matches every day within a multi-day range', function () {
    Event::factory()->create(['occurred_at' => '2022-06-02 09:00:00', 'ends_at' => '2022-06-04 18:00:00']);

    expect(TimelineEntry::query()->coveringDate('2022-06-03')->count())->toBe(1)
        ->and(TimelineEntry::query()->coveringDate('2022-06-05')->count())->toBe(0);
});

it('coveringAnniversary matches a mid-range month-day', function () {
    Event::factory()->create(['occurred_at' => '2022-06-02 09:00:00', 'ends_at' => '2022-06-04 18:00:00']);

    expect(TimelineEntry::query()->coveringAnniversary('06-03')->count())->toBe(1)
        ->and(TimelineEntry::query()->coveringAnniversary('06-05')->count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TimelineEntryRange`
Expected: FAIL (column `ends_at` missing / scope undefined).

- [ ] **Step 3: Create the migration**

`database/migrations/2026_07_07_000000_add_ends_at_to_timeline_entries.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table): void {
            $table->timestamp('ends_at')->nullable()->after('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table): void {
            $table->dropColumn('ends_at');
        });
    }
};
```

- [ ] **Step 4: Mirror `ends_at` in the observer**

In `app/Observers/TimelineEntryObserver.php`, the `saved` method's `updateOrCreate` second argument currently sets `['occurred_at' => $model->occurred_at]`. Change it to also mirror the optional end date (null for models without the column — `getAttribute` returns null when absent):

```php
$entry = $model->timelineEntry()->updateOrCreate(
    ['timelineable_type' => $model->getMorphClass(), 'timelineable_id' => $model->getKey()],
    ['occurred_at' => $model->occurred_at, 'ends_at' => $model->getAttribute('ends_at')],
);
```

Also add `ends_at` to `TimelineEntry`'s fillable/guarded handling if it uses `$fillable` (if the model is `$guarded = []`, no change needed — verify and add `'ends_at'` to `$fillable` only if a fillable array exists).

- [ ] **Step 5: Add the scopes to `TimelineEntry`**

In `app/Models/TimelineEntry.php`, add (import `Illuminate\Database\Eloquent\Builder` if not present):

```php
/**
 * Entries whose date span covers the given Y-m-d. Single-day entries
 * (ends_at null) collapse to their occurred_at day; multi-day events
 * match every day from occurred_at through ends_at inclusive.
 */
public function scopeCoveringDate(Builder $query, string $date): Builder
{
    return $query
        ->whereRaw('DATE(occurred_at) <= ?', [$date])
        ->whereRaw('DATE(COALESCE(ends_at, occurred_at)) >= ?', [$date]);
}

/**
 * Entries whose date span covers the given m-d in any year (for on-this-day).
 * Handles ranges within a single calendar year; a range crossing a month
 * boundary matches each covered month-day. Multi-year-spanning ranges are an
 * accepted edge (no current data spans them).
 */
public function scopeCoveringAnniversary(Builder $query, string $monthDay): Builder
{
    return $query
        ->whereRaw("strftime('%m-%d', occurred_at) <= ?", [$monthDay])
        ->whereRaw("strftime('%m-%d', COALESCE(ends_at, occurred_at)) >= ?", [$monthDay]);
}
```

- [ ] **Step 6: Migrate and run the tests**

Run: `php artisan migrate --force && php artisan test --compact --filter=TimelineEntryRange`
Expected: PASS (4 tests).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Observers/TimelineEntryObserver.php app/Models/TimelineEntry.php tests/Feature/TimelineEntryRangeTest.php
git commit -m "feat: mirror ends_at onto timeline entries with range-aware scopes"
```

---

### Task 2: Range-aware day and on-this-day views

**Files:**
- Modify: `app/Http/Controllers/TimelineController.php` (`day()` ~line 355-358; `onThisDay()` ~line 111-114)
- Test: `tests/Feature/MultiDayTimelineTest.php`

**Interfaces:**
- Consumes: `TimelineEntry::scopeCoveringDate`, `scopeCoveringAnniversary` (Task 1).
- Produces: `/YYYY/MM/DD` and `/on-this-day` include multi-day events on every covered day; counts unaffected.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MultiDayTimelineTest.php`:

```php
<?php

use App\Models\Event;
use App\Models\TimelineEntry;

it('shows a multi-day event on a middle day of its range', function () {
    Event::factory()->create([
        'name' => 'WordCamp Europe',
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => '2022-06-04 18:00:00',
    ]);

    $this->get('/2022/06/03')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Day')->has('items', 1));
});

it('counts a multi-day event once', function () {
    Event::factory()->create(['occurred_at' => '2022-06-02 09:00:00', 'ends_at' => '2022-06-04 18:00:00']);

    expect(TimelineEntry::query()->count())->toBe(1);
});

it('surfaces a multi-day event on a mid-range on-this-day', function () {
    // occurred 2 years ago, spanning today's month-day
    $year = (int) now()->format('Y') - 2;
    $start = sprintf('%d-%02d-%02d 09:00:00', $year, now()->month, max(1, now()->day - 1));
    $end = sprintf('%d-%02d-%02d 18:00:00', $year, now()->month, min(28, now()->day + 1));
    Event::factory()->create(['occurred_at' => $start, 'ends_at' => $end]);

    $this->get('/on-this-day')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('entriesCount', 1));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MultiDayTimeline`
Expected: FAIL (day 3 shows 0 items — current `whereDate` only matches the start day).

- [ ] **Step 3: Make `day()` range-aware**

In `TimelineController::day()`, replace:

```php
->whereDate('occurred_at', $date->toDateString())
```

with:

```php
->coveringDate($date->toDateString())
```

- [ ] **Step 4: Make `onThisDay()` range-aware**

In `TimelineController::onThisDay()`, replace:

```php
->whereMonth('occurred_at', $today->month)
->whereDay('occurred_at', $today->day)
```

with:

```php
->coveringAnniversary($today->format('m-d'))
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact --filter=MultiDayTimeline`
Expected: PASS (3 tests). Then run the existing timeline suite to confirm no regression: `php artisan test --compact --filter=Timeline`.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/TimelineController.php tests/Feature/MultiDayTimelineTest.php
git commit -m "feat: range-aware day and on-this-day views for multi-day events"
```

---

### Task 3: Multi-day range on the event payload + badge formatter

**Files:**
- Modify: `app/Models/Event.php` (add `dateRange(): ?array` and surface it in `card()`)
- Create: `resources/js/lib/eventDate.js` (badge label formatter, if not derivable server-side)
- Test: `tests/Feature/EventDateRangeTest.php`

**Interfaces:**
- Produces: `Event::dateRange(): ?array{start: string, end: string, days: int, label: string}` — null for single-day events (no `ends_at` or same calendar day). `label` is e.g. `"2-4 Jun 2022"`. `card()` gains a `'range'` key carrying this (or null).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/EventDateRangeTest.php`:

```php
<?php

use App\Models\Event;

it('returns a range for a multi-day event', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => '2022-06-04 18:00:00',
    ]);

    expect($event->dateRange())->toMatchArray([
        'days' => 3,
        'label' => '2-4 Jun 2022',
    ]);
});

it('returns null for a single-day event', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => null,
    ]);

    expect($event->dateRange())->toBeNull();
});

it('returns null when ends_at is the same day', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 18:00:00',
        'ends_at' => '2022-06-02 22:00:00',
    ]);

    expect($event->dateRange())->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EventDateRange`
Expected: FAIL (method `dateRange` not defined).

- [ ] **Step 3: Implement `dateRange()` on `Event`**

Add to `app/Models/Event.php` (uses Carbon; format keeps single spelled month when same, and no em dashes — a hyphen joins the day numbers):

```php
/**
 * The event's day span for multi-day display, or null when it is a single
 * day. `label` compacts a same-month range to "2-4 Jun 2022" and a
 * cross-month range to "30 Jun - 2 Jul 2022".
 *
 * @return array{start: string, end: string, days: int, label: string}|null
 */
public function dateRange(): ?array
{
    if ($this->ends_at === null || $this->ends_at->toDateString() === $this->occurred_at->toDateString()) {
        return null;
    }

    $start = $this->occurred_at;
    $end = $this->ends_at;
    $days = $start->startOfDay()->diffInDays($end->startOfDay()) + 1;

    $label = $start->format('n') === $end->format('n')
        ? $start->format('j').'-'.$end->format('j M Y')
        : $start->format('j M').' - '.$end->format('j M Y');

    return [
        'start' => $start->toDateString(),
        'end' => $end->toDateString(),
        'days' => (int) $days,
        'label' => $label,
    ];
}
```

Then add the key to the array returned by `card()`:

```php
'range' => $this->dateRange(),
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --compact --filter=EventDateRange`
Expected: PASS (3 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Event.php tests/Feature/EventDateRangeTest.php
git commit -m "feat: event dateRange for multi-day badge"
```

---

### Task 4: Static location-map generation for point models

**Files:**
- Create: `app/Actions/GenerateLocationMap.php`
- Create: `app/Console/Commands/Fetch/FetchEventMaps.php`
- Test: `tests/Feature/GenerateLocationMapTest.php`

**Interfaces:**
- Consumes: `config('services.mapbox.token')`.
- Produces: `GenerateLocationMap::__invoke(Model&HasMedia $model): ?Media` — reads `latitude`/`longitude`, requests a Mapbox static PNG with a pin, stores it in the model's `map` collection (`singleFile`), returns the `Media` or null (no coords / already present without `--force` semantics handled by caller). Command signature `events:maps {--force}`.

Rationale (spec deviation, lower risk): a sibling action for point markers rather than overloading the activity-only `GenerateStaticMap`, so activity route generation is untouched and the point action is reusable by checkins later.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/GenerateLocationMapTest.php`:

```php
<?php

use App\Actions\GenerateLocationMap;
use App\Models\Event;
use Illuminate\Support\Facades\Http;

it('generates and attaches a static pin map for an event with coordinates', function () {
    config()->set('services.mapbox.token', 'test-token');
    Http::fake(['api.mapbox.com/*' => Http::response('PNGBYTES', 200)]);

    $event = Event::factory()->create(['latitude' => 51.5129, 'longitude' => -0.1201]);

    $media = (new GenerateLocationMap)($event);

    expect($media)->not->toBeNull()
        ->and($event->fresh()->getFirstMedia('map'))->not->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'pin-')
        && str_contains($request->url(), '-0.1201,51.5129'));
});

it('returns null when the model has no coordinates', function () {
    config()->set('services.mapbox.token', 'test-token');
    $event = Event::factory()->create(['latitude' => null, 'longitude' => null]);

    expect((new GenerateLocationMap)($event))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GenerateLocationMap`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement the action**

`app/Actions/GenerateLocationMap.php`:

```php
<?php

namespace App\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GenerateLocationMap
{
    private const ZOOM = 14;

    public function __invoke(Model&HasMedia $model): ?Media
    {
        $lat = $model->getAttribute('latitude');
        $lng = $model->getAttribute('longitude');

        if ($lat === null || $lng === null) {
            return null;
        }

        $token = config('services.mapbox.token');

        if (! $token) {
            return null;
        }

        $marker = "pin-s+2E9E6A({$lng},{$lat})";
        $center = "{$lng},{$lat},".self::ZOOM;

        $url = "https://api.mapbox.com/styles/v1/mapbox/light-v11/static/{$marker}/{$center}/800x500@2x?access_token={$token}";

        $response = Http::get($url);

        if ($response->failed()) {
            return null;
        }

        return $model->addMediaFromString($response->body())
            ->usingFileName(Str::uuid().'.png')
            ->toMediaCollection('map');
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --compact --filter=GenerateLocationMap`
Expected: PASS (2 tests).

- [ ] **Step 5: Implement the backfill command**

`app/Console/Commands/Fetch/FetchEventMaps.php`:

```php
<?php

namespace App\Console\Commands\Fetch;

use App\Actions\GenerateLocationMap;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:maps {--force : Regenerate maps that already exist}')]
#[Description('Generate static location pin maps for events with coordinates')]
class FetchEventMaps extends Command
{
    public function handle(GenerateLocationMap $generate): int
    {
        $events = Event::query()->whereNotNull('latitude')->whereNotNull('longitude')->get();
        $done = 0;
        $skipped = 0;

        foreach ($events as $event) {
            if (! $this->option('force') && $event->getFirstMedia('map')) {
                $skipped++;

                continue;
            }

            if ($this->option('force')) {
                $event->clearMediaCollection('map');
            }

            if ($generate($event)) {
                $done++;
                $this->components->task("{$event->name}");
            }
        }

        $this->components->info("Generated {$done}, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Backfill the existing events**

Run: `php artisan events:maps`
Then process the media queue (conversions/attachment jobs): `php artisan queue:work --stop-when-empty`
Expected: "Generated 88, skipped 0." (or all with coordinates).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/GenerateLocationMap.php app/Console/Commands/Fetch/FetchEventMaps.php tests/Feature/GenerateLocationMapTest.php
git commit -m "feat: static location pin maps for events"
```

---

### Task 5: Generic `LocationMap.vue` interactive marker map

**Files:**
- Create: `resources/js/Components/Maps/LocationMap.vue`
- Test: none standalone — the component is exercised by Task 7's `tests/Browser/EventDetailSmokeTest.php` (interactive maps are covered via the browser smoke test rather than a unit test).

**Interfaces:**
- Produces: `<LocationMap :lat="Number" :lng="Number" :label="String?" :zoom="Number?" height-class="String?" />` — an interactive OpenFreeMap MapLibre map centred on the point with a single marker. Generic: no event-specific props, reusable by checkins.

- [ ] **Step 1: Create the component**

`resources/js/Components/Maps/LocationMap.vue` (models `EntryMap.vue`'s lazy MapLibre load, single marker instead of a route):

```vue
<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    lat: { type: Number, required: true },
    lng: { type: Number, required: true },
    label: { type: String, default: '' },
    zoom: { type: Number, default: 14 },
    heightClass: { type: String, default: 'h-64 sm:h-80' },
});

const MAPLIBRE_VERSION = '4.7.1';
const STYLE_URL = 'https://tiles.openfreemap.org/styles/positron';

const container = ref(null);
let map = null;

// Load the MapLibre stylesheet/script once, shared with EntryMap's loader pattern.
function loadStylesheet(href) {
    if (document.querySelector(`link[href="${href}"]`)) {
        return;
    }
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
}

function loadScript(src) {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${src}"]`);
        if (existing) {
            if (window.maplibregl) {
                resolve();
            } else {
                existing.addEventListener('load', resolve);
                existing.addEventListener('error', reject);
            }
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

onMounted(async () => {
    loadStylesheet(`https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.css`);
    if (!window.maplibregl) {
        try {
            await loadScript(`https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.js`);
        } catch (error) {
            console.error('[LocationMap] failed to load MapLibre', error);
            return;
        }
    }
    if (!window.maplibregl || !container.value) {
        return;
    }

    map = new window.maplibregl.Map({
        container: container.value,
        style: STYLE_URL,
        center: [props.lng, props.lat],
        zoom: props.zoom,
        attributionControl: true,
    });
    map.addControl(new window.maplibregl.NavigationControl({ showCompass: false }), 'top-right');
    new window.maplibregl.Marker({ color: '#2E9E6A' }).setLngLat([props.lng, props.lat]).addTo(map);
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <div
        ref="container"
        :class="heightClass"
        class="w-full overflow-hidden rounded-lg border border-neutral-100"
        role="img"
        :aria-label="label ? `Map showing ${label}` : 'Location map'"
    />
</template>
```

- [ ] **Step 2: Build assets**

Run: `npm run build`
Expected: builds without errors (component compiles).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Maps/LocationMap.vue
git commit -m "feat: generic LocationMap marker map component"
```

---

### Task 6: `EntryController` event payload (photos, location, Google link)

**Files:**
- Modify: `app/Http/Controllers/EntryController.php` (add `Event` import; extend `detailData` ~line 131; add a private `mapsUrl` helper)
- Test: `tests/Feature/EventEntryPayloadTest.php`

**Interfaces:**
- Consumes: `Event::galleryPhotos()` (existing on `HasAttachments`), `Event::dateRange()` (Task 3).
- Produces: for an `Event`, the entry props include `photos` (array), `location` (`{lat, lng, address, mapsUrl}` or absent when no coords), and `range` (from `card()`, already present via `cardItem`). `mapsUrl` = `https://www.google.com/maps/search/?api=1&query=<urlencoded address>`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/EventEntryPayloadTest.php`:

```php
<?php

use App\Models\Event;

it('exposes photos, location and a google maps url on the event entry', function () {
    $event = Event::factory()->create([
        'name' => 'Test Gig',
        'occurred_at' => '2022-06-02 19:00:00',
        'ends_at' => null,
        'venue_name' => 'Some Venue',
        'city' => 'London',
        'country' => 'United Kingdom',
        'latitude' => 51.5,
        'longitude' => -0.12,
        'meta' => ['address' => 'Some Venue, 1 Test St, London, UK'],
    ]);

    $this->get($event->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('entry.photos')
            ->where('entry.location.lat', 51.5)
            ->where('entry.location.mapsUrl', 'https://www.google.com/maps/search/?api=1&query='.urlencode('Some Venue, 1 Test St, London, UK')));
});

it('omits location when the event has no coordinates', function () {
    $event = Event::factory()->create(['latitude' => null, 'longitude' => null]);

    $this->get($event->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('entry.location', null));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EventEntryPayload`
Expected: FAIL (no `photos`/`location` on event props).

- [ ] **Step 3: Extend `detailData`**

In `app/Http/Controllers/EntryController.php`, add `use App\Models\Event;` with the other model imports. Then extend the photos condition and add the location block (place alongside the existing `if ($model instanceof Activity || $model instanceof Note)`):

```php
if ($model instanceof Activity || $model instanceof Note || $model instanceof Event) {
    $data['photos'] = $model->galleryPhotos();
}

if ($model instanceof Event && $model->latitude !== null && $model->longitude !== null) {
    $data['location'] = [
        'lat' => (float) $model->latitude,
        'lng' => (float) $model->longitude,
        'address' => $this->eventAddress($model),
        'mapsUrl' => 'https://www.google.com/maps/search/?api=1&query='.urlencode($this->eventAddress($model)),
    ];
}
```

Add the private helper to the controller:

```php
/**
 * Best available address string for maps: the geocoded address stored in
 * meta, else the venue/city/country the event carries.
 */
private function eventAddress(Event $event): string
{
    return $event->meta['address']
        ?? collect([$event->venue_name, $event->city, $event->country])->filter()->implode(', ');
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --compact --filter=EventEntryPayload`
Expected: PASS (2 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/EntryController.php tests/Feature/EventEntryPayloadTest.php
git commit -m "feat: event entry payload with photos, location and google maps link"
```

---

### Task 7: `EventDetail.vue` — gallery, map, address, multi-day badge

**Files:**
- Modify: `resources/js/Components/Entry/EventDetail.vue`
- Create: `tests/Browser/EventDetailSmokeTest.php`

**Interfaces:**
- Consumes: `entry.photos` (`{src, srcset, full}[]`), `entry.location` (`{lat, lng, address, mapsUrl}`), `entry.range` (`{label, days}` or null). Reuses `ActivityMedia`, `Lightbox`, `LocationMap`, `ExternalLink`.

- [ ] **Step 1: Rewrite `EventDetail.vue`**

Replace `resources/js/Components/Entry/EventDetail.vue` with (adds photos grid + lightbox, location map, Google link, and a multi-day badge; keeps existing detail rows/description/url):

```vue
<script setup>
import { computed, ref } from 'vue';
import DetailList from '../Ui/DetailList.vue';
import SectionHead from '../Ui/SectionHead.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import ActivityMedia from './ActivityMedia.vue';
import Lightbox from '../Overlays/Lightbox.vue';
import LocationMap from '../Maps/LocationMap.vue';
import { titleCase } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

// Loose, display-only details live in the meta JSON column (seat, geocoding extras).
const seat = computed(() => props.entry.meta?.seat ?? null);
// Photo gallery in {src,srcset,full} shape; empty when the event has no photos.
const photos = computed(() => (Array.isArray(props.entry.photos) ? props.entry.photos : []));
const location = computed(() => props.entry.location ?? null);
// Multi-day range badge data, null for single-day events.
const range = computed(() => props.entry.range ?? null);
const lightboxIndex = ref(null);

const rows = computed(() => [
    { label: 'Type', value: titleCase(props.entry.type) },
    { label: 'Organiser', value: props.entry.organiser },
    { label: 'Venue', value: props.entry.venue_name },
    { label: 'City', value: props.entry.city },
    { label: 'Country', value: props.entry.country },
    { label: 'Seat', value: seat.value },
]);
</script>

<template>
    <div class="space-y-8">
        <p v-if="range" class="inline-flex items-center gap-1.5 rounded-full bg-event/10 px-3 py-1 text-meta font-medium text-event">
            {{ range.label }} &middot; {{ range.days }} days
        </p>

        <ActivityMedia
            v-if="photos.length"
            :photos="photos"
            @open="lightboxIndex = $event"
        />
        <Lightbox v-model:index="lightboxIndex" :photos="photos" />

        <LocationMap v-if="location" :lat="location.lat" :lng="location.lng" :label="entry.venue_name" />

        <DetailList :rows="rows" />

        <div v-if="entry.description">
            <SectionHead title="Notes" />
            <p class="text-body text-neutral-700">{{ entry.description }}</p>
        </div>

        <div v-if="location">
            <ExternalLink :href="location.mapsUrl">View on Google Maps</ExternalLink>
        </div>

        <div v-if="entry.url">
            <ExternalLink :href="entry.url">More about this event</ExternalLink>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Write a browser smoke test**

Create `tests/Browser/EventDetailSmokeTest.php`:

```php
<?php

use App\Models\Event;

it('renders an event with a photo, map and google link without JS errors', function () {
    $event = Event::factory()->create([
        'name' => 'Smoke Gig',
        'occurred_at' => '2022-06-02 19:00:00',
        'venue_name' => 'Smoke Venue',
        'city' => 'London',
        'latitude' => 51.5,
        'longitude' => -0.12,
        'meta' => ['address' => 'Smoke Venue, London'],
    ]);
    $event->addMediaFromString('x')->usingFileName('c.jpg')->toMediaCollection('cover');

    $page = visit($event->url());

    $page->assertNoJavaScriptErrors()
        ->assertSee('Smoke Gig')
        ->assertSee('View on Google Maps');
});
```

- [ ] **Step 3: Build + run the smoke test**

Run: `npm run build && php artisan test --compact tests/Browser/EventDetailSmokeTest.php`
Expected: PASS (no JS errors; text present).

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Entry/EventDetail.vue tests/Browser/EventDetailSmokeTest.php
git commit -m "feat: event single-view with gallery, location map and google link"
```

---

### Task 8: Timeline card — event photos/static-map + multi-day badge

**Files:**
- Modify: `resources/js/Components/Timeline/FeedItem.vue` (badge for `airline`-style entries already exists; add a range badge and confirm events pass photos/map)
- Modify (if needed): `app/Actions/BuildTimelineFeed.php` (ensure `cardItem` includes `photos` and static `map` for events, mirroring activities)
- Test: `tests/Feature/EventCardTest.php`

**Interfaces:**
- Consumes: `Event` `card()['range']` (Task 3); `Event::galleryPhotos()`; the `map` collection (Task 4).
- Produces: an event card shows its cover photo (or static map) and, when multi-day, a range badge.

- [ ] **Step 1: Inspect the feed builder**

Read `app/Actions/BuildTimelineFeed.php` `cardItem()` to see how `photos` and the static `map` are attached for activities/flights. Confirm whether the card payload is type-gated. If events are excluded from the photo/map path, extend the same branch to include `Event` (photos via `galleryPhotos()`, static map via the `map` collection URL), matching the activity shape the card already renders.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/EventCardTest.php`:

```php
<?php

use App\Models\Event;

it('includes a photo and multi-day range on the event card payload', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => '2022-06-04 18:00:00',
    ]);
    $event->addMediaFromString('x')->usingFileName('c.jpg')->toMediaCollection('cover');

    $this->get('/2022/06/03')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('items.0.photos', 1)
            ->where('items.0.range.days', 3));
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=EventCard`
Expected: FAIL (event card lacks `photos`/`range`).

- [ ] **Step 4: Extend `cardItem` for events**

In `app/Actions/BuildTimelineFeed.php`, wherever the card payload attaches `photos`/`map`/`range` per model type, include `Event` in the same way activities are handled: set `photos` from `$model->galleryPhotos()`, the static `map` url from the `map` collection when there is no cover photo, and `range` from `$model->card()['range']`. (Exact edit depends on the file's current structure found in Step 1 — mirror the activity branch.)

- [ ] **Step 5: Add the range badge to `FeedItem.vue`**

In `resources/js/Components/Timeline/FeedItem.vue`, near where the airline/number caption renders, add a range badge when `range` is present on the item:

```vue
<span v-if="range" class="text-caption text-neutral-400">{{ range.label }} &middot; {{ range.days }} days</span>
```

Add `range: { type: Object, default: null }` to the component's `defineProps` and thread it from the item payload where the other card fields are read.

- [ ] **Step 6: Build + run the tests**

Run: `npm run build && php artisan test --compact --filter=EventCard`
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/BuildTimelineFeed.php resources/js/Components/Timeline/FeedItem.vue tests/Feature/EventCardTest.php
git commit -m "feat: event timeline cards with photos and multi-day badge"
```

---

## Final Verification

- [ ] Run the full suite: `php artisan test --compact`
- [ ] Confirm `event` accent colour token exists (`bg-event`, `text-event`) in the Tailwind theme; if not, use the existing event accent class used elsewhere (check `project_type_colours`).
- [ ] Visit an imported multi-day event (e.g. WordCamp Europe 2022) at its start and a middle day; confirm it appears on both, shows the map, gallery, Google link, and range badge, and that `/more` counts are unchanged.
