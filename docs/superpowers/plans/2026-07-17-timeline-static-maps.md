# Timeline static maps Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every located timeline type (event, activity, flight, fuel, checkin) shows a pre-generated, stored light+dark static map on the timeline (no Mapbox render at view time), and fuel/checkins gain the interactive single-entry map.

**Architecture:** Mirror the existing event pattern everywhere. Server-side generator actions fetch Mapbox static images (via the `App\Support\StaticMap` shape helpers) and store `map`/`map_dark` media; each model's `card()` serves the stored URLs through `meta.map`/`meta.mapDark`; `FeedItem` renders the stored images and drops its live `lib/staticMap.js` calls; `FuelDetail`/`CheckinDetail` render the interactive `LocationMap` from the controller-provided `location`.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 4, Inertia v3 + Vue 3, Tailwind v4, Spatie Media Library, Mapbox Static Images API.

## Global Constraints

- PHP 8.4: explicit return types; constructor property promotion; curly braces on all control structures; PHPDoc over inline comments.
- Vue/JS: comment functions, computeds, and non-obvious logic (this project wants Vue comments, unlike the terse-PHP rule). Single root element per component.
- Tailwind: only standard scale utilities, never arbitrary bracket values. Icons via `Components/Ui/Icon.vue` (aria-hidden). Focus-visible rings mirror every hover.
- Pest browser tests MUST assert rendered DOM elements, not text that also lives in the Inertia props JSON.
- Never `env()` outside config; use `Model::query()`, not `DB::`. Pin colours come from `App\Support\TypeColors::hex($token)`.
- After editing PHP run `vendor/bin/pint --dirty --format agent`; after editing JS/Vue the user runs the build. Run PHP tests with `php artisan test --compact --filter=<name>`.
- Conventional commits, no attribution footer. Branch: `feat/timeline-static-maps` (already created off `feat/fuel-station-lookup`).
- Stored maps use the `map` (light) and `map_dark` (dark) media collections, matching events. `getFirstMediaUrl('map')` reads them back.

---

### Task 1: Parameterise the pin generator colour

**Files:**
- Modify: `app/Actions/GenerateLocationMap.php`
- Test: `tests/Feature/GenerateLocationMapTest.php`

**Interfaces:**
- Produces: `GenerateLocationMap::__invoke(Model&HasMedia $model, ?string $markerColor = null): ?Media` — stores light (`map`) and dark (`map_dark`) pin images; `$markerColor` defaults to the event purple when null.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/GenerateLocationMapTest.php`:

```php
<?php

use App\Actions\GenerateLocationMap;
use App\Models\Checkin;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.mapbox.token' => 'test-token']);
});

it('stores light and dark pins using the given marker colour', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('PNGDATA', 200)]);

    $checkin = Checkin::factory()->create(['latitude' => 51.5, 'longitude' => -0.1]);

    app(GenerateLocationMap::class)($checkin, 'ff8800');

    expect($checkin->getFirstMediaUrl('map'))->not->toBe('');
    expect($checkin->getFirstMediaUrl('map_dark'))->not->toBe('');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'pin-l+ff8800')
        && str_contains($request->url(), 'light-v11'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'dark-v11'));
});

it('returns null when the model has no coordinates', function () {
    $checkin = Checkin::factory()->create(['latitude' => null, 'longitude' => null]);

    expect(app(GenerateLocationMap::class)($checkin, 'ff8800'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GenerateLocationMap`
Expected: FAIL (colour is hardcoded; second arg unsupported).

- [ ] **Step 3: Add the colour parameter**

In `app/Actions/GenerateLocationMap.php`, change the signature and the marker line. Replace:

```php
    public function __invoke(Model&HasMedia $model): ?Media
    {
        $lat = $model->getAttribute('latitude');
```

with:

```php
    public function __invoke(Model&HasMedia $model, ?string $markerColor = null): ?Media
    {
        $color = $markerColor ?? self::MARKER_COLOR;
        $lat = $model->getAttribute('latitude');
```

and replace the marker construction line:

```php
            $marker = 'pin-l+'.self::MARKER_COLOR."({$lng},{$lat})";
```

with:

```php
            $marker = 'pin-l+'.$color."({$lng},{$lat})";
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=GenerateLocationMap`
Expected: PASS (2 tests).

- [ ] **Step 5: Verify events still generate (unchanged default)**

Run: `php artisan test --compact --filter=EventMaps` (if such a test exists; otherwise skip).
Expected: PASS or no matching tests.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/GenerateLocationMap.php tests/Feature/GenerateLocationMapTest.php
git commit -m "feat: parameterise location-map pin colour"
```

---

### Task 2: Store light+dark for the activity route map

**Files:**
- Modify: `app/Actions/GenerateStaticMap.php`
- Test: `tests/Feature/GenerateStaticMapTest.php`

**Interfaces:**
- Produces: `GenerateStaticMap::__invoke(Activity $activity): ?Media` — stores light (`map`) and dark (`map_dark`) route images built via `App\Support\StaticMap::route()`. Returns the last stored media (dark), or null when there is no polyline.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/GenerateStaticMapTest.php`:

```php
<?php

use App\Actions\GenerateStaticMap;
use App\Models\Activity;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.mapbox.token' => 'test-token']);
});

it('stores light and dark route maps from the activity polyline', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('PNGDATA', 200)]);

    $activity = Activity::factory()->create(['meta' => ['polyline' => '_p~iF~ps|U_ulLnnqC']]);

    app(GenerateStaticMap::class)($activity);

    expect($activity->getFirstMediaUrl('map'))->not->toBe('');
    expect($activity->getFirstMediaUrl('map_dark'))->not->toBe('');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'light-v11'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'dark-v11'));
});

it('returns null when the activity has no polyline', function () {
    $activity = Activity::factory()->create(['meta' => []]);

    expect(app(GenerateStaticMap::class)($activity))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GenerateStaticMap`
Expected: FAIL (only a light `map` is stored; no `map_dark`).

- [ ] **Step 3: Rewrite the action to store both styles via StaticMap::route**

Replace the body of `app/Actions/GenerateStaticMap.php` with:

```php
<?php

namespace App\Actions;

use App\Models\Activity;
use App\Support\StaticMap;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GenerateStaticMap
{
    public function __invoke(Activity $activity): ?Media
    {
        $polyline = $activity->meta['polyline'] ?? null;

        if (! $polyline) {
            return null;
        }

        $styles = [
            'map' => StaticMap::route($polyline),
            'map_dark' => StaticMap::route($polyline, style: 'mapbox/dark-v11'),
        ];

        $last = null;

        foreach ($styles as $collection => $url) {
            if ($url === null) {
                continue;
            }

            try {
                $response = Http::get($url);
            } catch (ConnectionException) {
                continue;
            }

            if ($response->failed()) {
                continue;
            }

            $last = $activity->addMediaFromString($response->body())
                ->usingFileName(Str::uuid().'.png')
                ->toMediaCollection($collection);
        }

        return $last;
    }
}
```

- [ ] **Step 4: Add a `style` parameter to `StaticMap::route`**

In `app/Support/StaticMap.php`, change the `route` signature and its style usage. Replace:

```php
    public static function route(?string $polyline, string $color = '2e9e6a', int $width = 1200, int $height = 630, int $padding = 64): ?string
    {
        if (! $polyline) {
            return null;
        }

        $overlay = self::pathOverlay($polyline, 5, $color, '0.85');

        return sprintf(
            'https://api.mapbox.com/styles/v1/%s/static/%s/auto/%dx%d@2x?padding=%d&attribution=false&logo=false&access_token=%s',
            self::STYLE, $overlay, $width, $height, $padding, config('services.mapbox.token')
        );
    }
```

with:

```php
    public static function route(?string $polyline, string $color = '2e9e6a', int $width = 1200, int $height = 630, int $padding = 64, string $style = self::STYLE): ?string
    {
        if (! $polyline) {
            return null;
        }

        $overlay = self::pathOverlay($polyline, 5, $color, '0.85');

        return sprintf(
            'https://api.mapbox.com/styles/v1/%s/static/%s/auto/%dx%d@2x?padding=%d&attribution=false&logo=false&access_token=%s',
            $style, $overlay, $width, $height, $padding, config('services.mapbox.token')
        );
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=GenerateStaticMap`
Expected: PASS (2 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/GenerateStaticMap.php app/Support/StaticMap.php tests/Feature/GenerateStaticMapTest.php
git commit -m "feat: store light and dark activity route maps"
```

---

### Task 3: Flight arc map generator (light+dark)

**Files:**
- Create: `app/Actions/GenerateFlightMap.php`
- Test: `tests/Feature/GenerateFlightMapTest.php`

**Interfaces:**
- Consumes: `App\Support\StaticMap::arc(?float $originLng, ?float $originLat, ?float $destLng, ?float $destLat, string $color, int $width, int $height, int $padding, string $style)` — NOTE: `arc()` currently has no `$style` parameter; add it in Step 3 the same way `route()` got one.
- Produces: `GenerateFlightMap::__invoke(App\Models\Flight $flight): ?Media` — stores light (`map`) and dark (`map_dark`) arc images from the flight's origin/destination airport coordinates. Returns the last stored media, or null when either endpoint lacks coordinates.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/GenerateFlightMapTest.php`:

```php
<?php

use App\Actions\GenerateFlightMap;
use App\Models\Flight;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.mapbox.token' => 'test-token']);
});

it('stores light and dark arc maps from the flight endpoints', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('PNGDATA', 200)]);

    // FlightFactory sets origin_iata/destination_iata to real IATA codes; the
    // origin/destination belongsTo Airport (Sushi-backed by database/lookups),
    // so the loaded relations resolve latitude/longitude.
    $flight = Flight::factory()->create();

    app(GenerateFlightMap::class)($flight->load(['origin', 'destination']));

    expect($flight->getFirstMediaUrl('map'))->not->toBe('');
    expect($flight->getFirstMediaUrl('map_dark'))->not->toBe('');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'light-v11'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'dark-v11'));
});
```

NOTE for the implementer: if a factory-created flight's `origin`/`destination` do not
resolve coordinates (e.g. the random IATA code is absent from the lookup CSV), pin the
factory to a known code pair with coordinates in the test. `Flight` already implements
`HasMedia` via `HasAttachments`, so no trait change is needed.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GenerateFlightMap`
Expected: FAIL (class does not exist).

- [ ] **Step 3: Add a `style` parameter to `StaticMap::arc`**

In `app/Support/StaticMap.php`, add `string $style = self::STYLE` as the final parameter of `arc()` and use `$style` in place of `self::STYLE` in that method's final `sprintf` (same change pattern as `route()` in Task 2).

- [ ] **Step 4: Create the action**

Create `app/Actions/GenerateFlightMap.php`:

```php
<?php

namespace App\Actions;

use App\Models\Flight;
use App\Support\StaticMap;
use App\Support\TypeColors;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GenerateFlightMap
{
    public function __invoke(Flight $flight): ?Media
    {
        $origin = $flight->origin;
        $destination = $flight->destination;

        if ($origin?->latitude === null || $destination?->latitude === null) {
            return null;
        }

        $color = TypeColors::hex('flight');

        $styles = [
            'map' => StaticMap::arc((float) $origin->longitude, (float) $origin->latitude, (float) $destination->longitude, (float) $destination->latitude, $color),
            'map_dark' => StaticMap::arc((float) $origin->longitude, (float) $origin->latitude, (float) $destination->longitude, (float) $destination->latitude, $color, style: 'mapbox/dark-v11'),
        ];

        $last = null;

        foreach ($styles as $collection => $url) {
            if ($url === null) {
                continue;
            }

            try {
                $response = Http::get($url);
            } catch (ConnectionException) {
                continue;
            }

            if ($response->failed()) {
                continue;
            }

            $last = $flight->addMediaFromString($response->body())
                ->usingFileName(Str::uuid().'.png')
                ->toMediaCollection($collection);
        }

        return $last;
    }
}
```

NOTE: confirm `Flight` implements `HasMedia` (uses `HasAttachments`); if not, add the
trait/interface the same way `Fuel`/`Event` do, and report it as a concern.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=GenerateFlightMap`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/GenerateFlightMap.php app/Support/StaticMap.php tests/Feature/GenerateFlightMapTest.php
git commit -m "feat: add flight arc map generator (light and dark)"
```

---

### Task 4: Emit stored map/mapDark from card() for activity, flight, fuel, checkin

**Files:**
- Modify: `app/Models/Activity.php` (card meta)
- Modify: `app/Models/Flight.php` (card meta)
- Modify: `app/Models/Fuel.php` (card meta)
- Modify: `app/Models/Checkin.php` (card meta)
- Test: `tests/Feature/CardMapTest.php`

**Interfaces:**
- Produces: each model's `card()['meta']` contains `map` and `mapDark` (stored URLs or null). `BuildTimelineFeed::cardItem` already maps `meta.map`/`meta.mapDark` to the `FeedItem` `map`/`mapDark` props (no change needed there).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CardMapTest.php`:

```php
<?php

use App\Models\Fuel;

it('exposes a stored map url on the fuel card when media is attached', function () {
    $fuel = Fuel::factory()->create(['station_name' => 'Test Garage']);
    $fuel->addMediaFromString('PNG')->usingFileName('m.png')->toMediaCollection('map');

    $meta = $fuel->card()['meta'];

    expect($meta['map'])->not->toBeNull();
});

it('has a null map on the fuel card when no media is attached', function () {
    $fuel = Fuel::factory()->create();

    expect($fuel->card()['meta']['map'])->toBeNull();
    expect($fuel->card()['meta']['mapDark'])->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CardMap`
Expected: FAIL (`meta` has no `map` key on the fuel card).

- [ ] **Step 3: Add map/mapDark to Fuel::card() meta**

In `app/Models/Fuel.php`, replace `'meta' => [],` in `card()` with:

```php
            'meta' => [
                'map' => $this->getFirstMediaUrl('map') ?: null,
                'mapDark' => $this->getFirstMediaUrl('map_dark') ?: null,
            ],
```

- [ ] **Step 4: Add map/mapDark to Checkin::card() meta**

In `app/Models/Checkin.php`, replace `'meta' => [],` in `card()` with the same block as Step 3.

- [ ] **Step 5: Add map/mapDark to Activity::card() meta**

In `app/Models/Activity.php` `card()`, extend the `meta` array so it reads:

```php
            'meta' => [
                'polyline' => data_get($this->meta, 'polyline'),
                'photos' => $this->galleryPhotos(),
                'map' => $this->getFirstMediaUrl('map') ?: null,
                'mapDark' => $this->getFirstMediaUrl('map_dark') ?: null,
            ],
```

- [ ] **Step 6: Add map/mapDark to Flight::card() meta**

In `app/Models/Flight.php` `card()`, add these two keys to the `meta` array (alongside `route`):

```php
                'map' => $this->getFirstMediaUrl('map') ?: null,
                'mapDark' => $this->getFirstMediaUrl('map_dark') ?: null,
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact --filter=CardMap`
Expected: PASS (2 tests).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Activity.php app/Models/Flight.php app/Models/Fuel.php app/Models/Checkin.php tests/Feature/CardMapTest.php
git commit -m "feat: serve stored map urls from activity, flight, fuel, checkin cards"
```

---

### Task 5: `maps:generate` backfill command

**Files:**
- Create: `app/Console/Commands/Fetch/GenerateEntryMaps.php`
- Test: `tests/Feature/GenerateEntryMapsTest.php`

**Interfaces:**
- Consumes: `GenerateLocationMap`, `GenerateStaticMap`, `GenerateFlightMap`, `App\Support\TypeColors`.
- Signature: `maps:generate {type : activity|flight|fuel|checkin} {--force}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/GenerateEntryMapsTest.php`:

```php
<?php

use App\Models\Fuel;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.mapbox.token' => 'test-token']);
});

it('generates pin maps for fuel rows with coordinates', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('PNG', 200)]);

    $fuel = Fuel::factory()->create(['latitude' => 51.3, 'longitude' => -0.1]);
    Fuel::factory()->create(['latitude' => null, 'longitude' => null]);

    $this->artisan('maps:generate', ['type' => 'fuel'])->assertSuccessful();

    expect($fuel->fresh()->getFirstMediaUrl('map'))->not->toBe('');
});

it('skips rows that already have a map unless forced', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('PNG', 200)]);

    $fuel = Fuel::factory()->create(['latitude' => 51.3, 'longitude' => -0.1]);
    $fuel->addMediaFromString('PNG')->usingFileName('m.png')->toMediaCollection('map');

    $this->artisan('maps:generate', ['type' => 'fuel'])->assertSuccessful();

    expect($fuel->fresh()->getMedia('map'))->toHaveCount(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GenerateEntryMaps`
Expected: FAIL (command not defined).

- [ ] **Step 3: Create the command**

Create `app/Console/Commands/Fetch/GenerateEntryMaps.php`:

```php
<?php

namespace App\Console\Commands\Fetch;

use App\Actions\GenerateFlightMap;
use App\Actions\GenerateLocationMap;
use App\Actions\GenerateStaticMap;
use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Fuel;
use App\Support\TypeColors;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('maps:generate {type : activity|flight|fuel|checkin} {--force : Regenerate maps that already exist}')]
#[Description('Generate and store static timeline maps for a located entry type')]
class GenerateEntryMaps extends Command
{
    public function handle(GenerateLocationMap $pin, GenerateStaticMap $route, GenerateFlightMap $arc): int
    {
        $type = $this->argument('type');
        $force = (bool) $this->option('force');

        [$query, $generate] = match ($type) {
            'fuel' => [Fuel::query()->whereNotNull('latitude'), fn ($m) => $pin($m, TypeColors::hex('fuel'))],
            'checkin' => [Checkin::query()->whereNotNull('latitude'), fn ($m) => $pin($m, TypeColors::hex('checkin'))],
            'activity' => [Activity::query(), fn ($m) => $route($m)],
            'flight' => [Flight::query()->with(['origin', 'destination']), fn ($m) => $arc($m)],
            default => [null, null],
        };

        if ($query === null) {
            $this->error("Unknown type: {$type}. Use activity, flight, fuel, or checkin.");

            return self::FAILURE;
        }

        $done = 0;
        $skipped = 0;

        foreach ($query->get() as $model) {
            if (! $force && $model->getFirstMedia('map')) {
                $skipped++;

                continue;
            }

            if ($generate($model)) {
                $done++;
            }
        }

        $this->info("Generated {$done}, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=GenerateEntryMaps`
Expected: PASS (2 tests).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/Fetch/GenerateEntryMaps.php tests/Feature/GenerateEntryMapsTest.php
git commit -m "feat: add maps:generate backfill command"
```

---

### Task 6: FeedItem renders stored images only

**Files:**
- Modify: `resources/js/Components/Timeline/FeedItem.vue`
- Test: `tests/Browser/TimelineMapTest.php` (Pest browser)

**Interfaces:**
- Consumes: `map` / `mapDark` props (stored URLs) from `cardItem`. No longer imports `lib/staticMap.js`.

- [ ] **Step 1: Write the failing test**

Create `tests/Browser/TimelineMapTest.php`:

```php
<?php

use App\Models\Fuel;

it('shows the stored fuel map image on the timeline', function () {
    $fuel = Fuel::factory()->create(['station_name' => 'Test Garage', 'occurred_at' => now()]);
    $fuel->addMediaFromString('PNG')->usingFileName('m.png')->toMediaCollection('map');

    $page = visit('/');

    // Assert the rendered <img>, not the props JSON: a fuel card map image is present.
    $page->assertPresent('img[src*="/storage"]');
});
```

NOTE: adjust the URL (`/` or the timeline route) and the selector to this app's markup
after reading `FeedItem.vue`'s rendered map `<img>`. The assertion must target a rendered
DOM element (per the project's browser-test rule), not text also present in props JSON.

- [ ] **Step 2: Run test to verify it fails or is red for the right reason**

Run: `php artisan test --compact --filter=TimelineMap`
Expected: FAIL until `card()` media (Task 4) and the img render path are in place. If the
selector needs adjusting, refine it against the real markup.

- [ ] **Step 3: Replace the live-render computeds with the stored props**

In `resources/js/Components/Timeline/FeedItem.vue`:

- Remove the import line:
  ```js
  import { staticRouteMap, staticArcMap, MAPBOX_DARK } from '../../lib/staticMap.js';
  ```
- Replace the `routeImageUrl` computed with one that uses the stored prop only:
  ```js
  // Stored static map for this entry (activity route, flight arc, event/fuel/checkin
  // pin), pre-generated server-side. Shown only when there is no cover photo.
  const routeImageUrl = computed(() => props.map ?? null);
  ```
- Replace the `routeImageDarkUrl` computed with:
  ```js
  // Dark twin of the stored map; the two <img> swap via dark:hidden / dark:block.
  const routeImageDarkUrl = computed(() => props.mapDark ?? null);
  ```
- Leave `banner`, `routePath`, `flightArc`, and the `RouteThumb` fallback untouched: the
  SVG thumbnail still renders when a stored image is absent (`v-if="banner && !routeImageUrl"`).

- [ ] **Step 4: Confirm staticMap.js has no other importers**

Run: `grep -rn "lib/staticMap" resources/js`
Expected: no matches after the edit. If none remain, delete `resources/js/lib/staticMap.js` and include it in this commit; if other importers exist, leave the file and note it.

- [ ] **Step 5: Build and run the browser test**

Run: `npm run build` then `php artisan test --compact --filter=TimelineMap`
Expected: PASS (the stored map `<img>` renders).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/Timeline/FeedItem.vue tests/Browser/TimelineMapTest.php
git rm resources/js/lib/staticMap.js   # only if Step 4 found no importers
git commit -m "refactor: render stored timeline maps, drop live mapbox render"
```

---

### Task 7: Single-entry maps for fuel and checkin

**Files:**
- Modify: `app/Http/Controllers/EntryController.php` (generalise the `location` block)
- Modify: `resources/js/Components/Entry/FuelDetail.vue`
- Modify: `resources/js/Components/Entry/CheckinDetail.vue`
- Test: `tests/Browser/FuelEntryMapTest.php`

**Interfaces:**
- Produces: `entry.location = {lat, lng, address, mapsUrl}` for fuel and checkin entries (in addition to events).

- [ ] **Step 1: Write the failing test**

Create `tests/Browser/FuelEntryMapTest.php`:

```php
<?php

use App\Models\Fuel;

it('renders the location map and garage fields on a fuel entry', function () {
    $fuel = Fuel::factory()->create([
        'station_name' => 'Godstone Road SF Connect',
        'brand' => 'BP',
        'postcode' => 'CR3 0EG',
        'latitude' => 51.31345,
        'longitude' => -0.08194,
        'occurred_at' => now(),
    ]);

    $page = visit($fuel->timelineEntry->url());

    // Assert a RENDERED element, not text also present in the Inertia props JSON:
    // the "View on Google Maps" ExternalLink renders an <a> only when the location
    // block (which contains the map) is present. Matching the anchor by href proves
    // FuelDetail rendered the location section, avoiding the props-JSON false positive.
    $page->assertPresent('a[href*="google.com/maps"]');
});
```

NOTE: confirm the single-entry URL for a fuel model (`$fuel->timelineEntry->url()` or the
model's `url()`), and that `ExternalLink` renders a real `<a href>`. If you want to also
assert the map container specifically, add a `data-testid="location-map"` to the
`LocationMap` wrapper div and assert `[data-testid="location-map"]` (the runtime
`.maplibregl-map` class is not reliable in the test env since tiles load from a CDN).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FuelEntryMap`
Expected: FAIL (no map, brand/postcode not shown).

- [ ] **Step 3: Generalise the EntryController location block**

In `app/Http/Controllers/EntryController.php`, the `location` object is currently built
only inside `if ($model instanceof Event)`. Add an equivalent for located non-event
models. After the existing Event block, add:

```php
        if (! $model instanceof Event && $model->getAttribute('latitude') !== null && $model->getAttribute('longitude') !== null) {
            $address = trim(implode(', ', array_filter([
                $model->getAttribute('station_name') ?? $model->getAttribute('venue_name'),
                $model->getAttribute('address'),
                $model->getAttribute('postcode'),
                $model->getAttribute('city'),
            ])));

            $data['location'] = [
                'lat' => (float) $model->getAttribute('latitude'),
                'lng' => (float) $model->getAttribute('longitude'),
                'address' => $address,
                'mapsUrl' => 'https://www.google.com/maps/search/?api=1&query='.urlencode($address !== '' ? $address : $model->getAttribute('latitude').','.$model->getAttribute('longitude')),
            ];
        }
```

NOTE: read the surrounding method first; `station_name` applies to fuel and `venue_name`
to checkin, so the `array_filter` picks whichever exists. Confirm the model attributes are
available on `$model` at this point (the method already reads `$model->latitude` for events).

- [ ] **Step 4: Add the map + garage fields to FuelDetail.vue**

Rewrite `resources/js/Components/Entry/FuelDetail.vue` to add a `LocationMap` and the
garage fields, keeping the existing stats:

```vue
<script setup>
import { computed } from 'vue';
import StatGrid from '../Stats/StatGrid.vue';
import DetailList from '../Ui/DetailList.vue';
import LocationMap from '../Maps/LocationMap.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import { number } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

// Fuel-purchase figures shown as display stats (blanks dropped by StatGrid).
const stats = computed(() => [
    { label: 'Volume', value: number(props.entry.litres, 1), unit: 'L' },
    { label: 'Cost', value: props.entry.cost ? `£${number(props.entry.cost, 2)}` : null },
    { label: 'Per litre', value: props.entry.price_per_litre ? `£${number(props.entry.price_per_litre, 3)}` : null },
    { label: 'Odometer', value: number(props.entry.odometer), unit: 'mi' },
]);

// Garage identity + address rows; StatGrid/DetailList drop the blank ones.
const rows = computed(() => [
    { label: 'Brand', value: props.entry.brand },
    { label: 'Address', value: props.entry.address },
    { label: 'Postcode', value: props.entry.postcode },
    { label: 'City', value: props.entry.city },
    { label: 'Fuel card cost', value: props.entry.fuel_card_cost ? `£${number(props.entry.fuel_card_cost, 2)}` : null },
]);

// Coordinate object the controller attaches for located entries; null otherwise.
const location = computed(() => props.entry.location ?? null);
</script>

<template>
    <div class="space-y-8">
        <div v-if="location" class="space-y-3">
            <LocationMap
                :lat="location.lat"
                :lng="location.lng"
                :label="entry.station_name || location.address"
                color="var(--color-fuel)"
            />
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <p class="text-meta text-neutral-600">
                    <span v-if="entry.station_name" class="font-medium text-neutral-900">{{ entry.station_name }}</span><span v-if="entry.city">{{ entry.station_name ? ', ' : '' }}{{ entry.city }}</span>
                </p>
                <ExternalLink :href="location.mapsUrl" label="View on Google Maps" />
            </div>
        </div>

        <StatGrid :stats="stats" />
        <DetailList :rows="rows" />
    </div>
</template>
```

- [ ] **Step 5: Add the map to CheckinDetail.vue**

In `resources/js/Components/Entry/CheckinDetail.vue`, import `LocationMap` and add a
`location` computed (`props.entry.location ?? null`), then render the map above the
existing `DetailList` when `location` is set:

```vue
        <div v-if="location" class="space-y-3">
            <LocationMap
                :lat="location.lat"
                :lng="location.lng"
                :label="entry.venue_name || location.address"
                color="var(--color-checkin)"
            />
        </div>
```

Add to the `<script setup>`:

```js
import LocationMap from '../Maps/LocationMap.vue';
// Coordinate object the controller attaches for located entries; null otherwise.
const location = computed(() => props.entry.location ?? null);
```

- [ ] **Step 6: Build and run the test**

Run: `npm run build` then `php artisan test --compact --filter=FuelEntryMap`
Expected: PASS (map container present, brand/postcode shown).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/EntryController.php resources/js/Components/Entry/FuelDetail.vue resources/js/Components/Entry/CheckinDetail.vue tests/Browser/FuelEntryMapTest.php
git commit -m "feat: interactive location map and garage fields on fuel and checkin entries"
```

---

### Task 8: Backfill and visual-parity check (manual, not a test)

**Files:** none (operational).

- [ ] **Step 1: Backfill stored maps for existing rows**

```bash
php artisan maps:generate fuel
php artisan maps:generate activity
php artisan maps:generate flight
# checkins: 0 in the dev DB; run in production where checkins exist.
```

- [ ] **Step 2: Visual-parity check**

Load the timeline and compare an activity and a flight card before/after (git stash the
branch or compare against `master`). Confirm the stored route/arc images match the
previous live-rendered look closely. Screenshot the timeline and a fuel single-entry page.

- [ ] **Step 3: Confirm the regenerated media is not committed**

Media files are stored via the media pipeline (not tracked source). Confirm `git status`
shows no stray committed binaries before finishing the branch.
