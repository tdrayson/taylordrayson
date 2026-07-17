# Activity Photo Map Markers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show Strava activity photos as circular markers on the activity detail route map, positioned where each photo was taken.

**Architecture:** Strava photos carry no coordinates and their EXIF is stripped, so position is derived by interpolating each photo's UTC `created_at` against the activity's `time`/`latlng` GPS stream at sync time. The resulting `[lat, lng]` is stored on the photo as Media Library custom properties, so nothing is computed at render time and the map component never learns Strava exists.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 4, spatie/laravel-medialibrary 11.23.1, Vue 3 + Inertia 3, MapLibre GL 4.7.1, Tailwind CSS 4.

**Spec:** `docs/superpowers/specs/2026-07-17-activity-photo-map-markers-design.md`

## Global Constraints

- Explicit return type declarations on every method and function.
- PHP 8 constructor property promotion in `__construct()`.
- Curly braces on all control structures, even single-line bodies.
- Prefer PHPDoc blocks over inline comments in PHP; comment Vue/JS functions, computeds and non-obvious logic.
- Run `vendor/bin/pint --dirty --format agent` before finalising any task touching PHP.
- Run tests with `php artisan test --compact --filter=<name>`.
- Never use em dashes in code, comments, commit messages or output.
- No arbitrary Tailwind bracket values; use the standard scale and existing theme tokens.
- Browser tests must assert rendered DOM elements, never text that also appears in the Inertia props JSON.
- `--color-activity` is an existing theme token with light and dark values (`resources/css/app.css:172`, `:249`), so `ring-activity` is valid and theme-aware.
- Coordinate order: Strava `latlng` and `LocatePhotoOnRoute` use `[lat, lng]`; MapLibre `setLngLat()` and the decoded polyline use `[lng, lat]`. The flip happens once, at the `setLngLat()` call site.

## File Structure

| File | Responsibility |
|---|---|
| `app/Services/Strava.php` | Add `activityStreams()`. HTTP only, returns decoded JSON. |
| `app/Actions/LocatePhotoOnRoute.php` | New. Pure interpolation maths. No HTTP, no Eloquent. |
| `app/Actions/FetchStravaActivitySummaries.php` | New. Pages the activity list once, returns `start_date` + `total_photo_count` keyed by Strava id. Shared by both commands. |
| `app/Actions/SyncStravaPhotos.php` | Locate each photo, write coordinates into custom properties. |
| `app/Console/Commands/Sync/StravaPhotos.php` | Fetch streams beside photos; stay resilient when streams fail. |
| `app/Console/Commands/Sync/StravaPhotoLocations.php` | New. Backfill coordinates onto existing media without re-downloading images. |
| `app/Models/Concerns/HasAttachments.php` | Expose `latitude`/`longitude` from `galleryPhotos()`. |
| `resources/js/Components/Maps/PhotoMarker.vue` | New. The circular marker button. |
| `resources/js/Components/Maps/EntryMap.vue` | Accept `photos`, attach MapLibre markers, emit `open-photo`. |
| `resources/js/Components/Entry/ActivityMedia.vue` | Pass photos to the map, forward the event to the existing `open`. |

`ActivityDetail.vue` needs no change: it already passes `:photos="photos"` to `ActivityMedia` and wires `@open="lightboxIndex = $event"` into the Lightbox.

---

### Task 1: Strava::activityStreams()

**Files:**
- Modify: `app/Services/Strava.php` (add after `activityPhotos()`, line 94)
- Test: `tests/Feature/StravaStreamsTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `Strava::activityStreams(int|string $id, array $keys = ['time', 'latlng']): ?array` returning the raw decoded streams JSON keyed by type (`['time' => ['data' => [...]], 'latlng' => ['data' => [...]]]`), or `null` on request failure.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/StravaStreamsTest.php`:

```php
<?php

use App\Services\Strava;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.strava.client_id' => 'cid',
        'services.strava.client_secret' => 'secret',
        'services.strava.refresh_token' => 'refresh',
    ]);
    Cache::flush();
});

it('requests the time and latlng streams keyed by type', function () {
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/streams*' => Http::response([
            'time' => ['data' => [0, 1, 2]],
            'latlng' => ['data' => [[51.1, -0.1], [51.2, -0.2], [51.3, -0.3]]],
        ]),
    ]);

    $streams = app(Strava::class)->activityStreams(123);

    expect($streams['time']['data'])->toBe([0, 1, 2])
        ->and($streams['latlng']['data'][0])->toBe([51.1, -0.1]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/activities/123/streams')
            && str_contains($request->url(), 'keys=time%2Clatlng')
            && str_contains($request->url(), 'key_by_type=true');
    });
});

it('returns null when the streams request fails', function () {
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/streams*' => Http::response([], 500),
    ]);

    expect(app(Strava::class)->activityStreams(123))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=StravaStreamsTest`
Expected: FAIL with `Call to undefined method App\Services\Strava::activityStreams()`

- [ ] **Step 3: Write minimal implementation**

In `app/Services/Strava.php`, add after `activityPhotos()`:

```php
    /**
     * The requested streams for an activity, keyed by stream type.
     *
     * @param  array<int, string>  $keys
     * @return array<string, array{data: array<int, mixed>}>|null Null on a request failure.
     */
    public function activityStreams(int|string $id, array $keys = ['time', 'latlng']): ?array
    {
        return $this->getJson(self::BASE."/api/v3/activities/{$id}/streams", [
            'keys' => implode(',', $keys),
            'key_by_type' => 'true',
        ]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=StravaStreamsTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Strava.php tests/Feature/StravaStreamsTest.php
git commit -m "feat: add Strava activity streams endpoint to the API client"
```

---

### Task 2: LocatePhotoOnRoute

The pure interpolation maths. Highest-value tests in the plan: no HTTP, no database, all edge cases cheap to cover.

**Files:**
- Create: `app/Actions/LocatePhotoOnRoute.php`
- Test: `tests/Unit/LocatePhotoOnRouteTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `LocatePhotoOnRoute::__invoke(CarbonImmutable $capturedAt, CarbonImmutable $activityStart, array $timeStream, array $latlngStream): ?array` returning `[float $lat, float $lng]` or `null`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/LocatePhotoOnRouteTest.php`:

```php
<?php

use App\Actions\LocatePhotoOnRoute;
use Carbon\CarbonImmutable;

/** A 5-point stream one second apart, starting at the given UTC time. */
function routeStart(): CarbonImmutable
{
    return CarbonImmutable::parse('2023-10-31T21:00:00Z');
}

function timeStream(): array
{
    return [0, 10, 20, 30, 40];
}

function latlngStream(): array
{
    return [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2], [51.3, -0.3], [51.4, -0.4]];
}

function locate(string $capturedAt, array $time = null, array $latlng = null): ?array
{
    return (new LocatePhotoOnRoute)(
        CarbonImmutable::parse($capturedAt),
        routeStart(),
        $time ?? timeStream(),
        $latlng ?? latlngStream(),
    );
}

it('returns the exact point when the photo matches a sample', function () {
    expect(locate('2023-10-31T21:00:20Z'))->toBe([51.2, -0.2]);
});

it('returns lat then lng, not lng then lat', function () {
    [$lat, $lng] = locate('2023-10-31T21:00:20Z');

    expect($lat)->toBe(51.2)  // northern hemisphere latitude
        ->and($lng)->toBe(-0.2); // near-Greenwich longitude
});

it('returns the nearest sample when the photo falls between two', function () {
    // 22s is closer to the 20s sample than the 30s one.
    expect(locate('2023-10-31T21:00:22Z'))->toBe([51.2, -0.2]);

    // 28s is closer to the 30s sample.
    expect(locate('2023-10-31T21:00:28Z'))->toBe([51.3, -0.3]);
});

it('returns the earlier sample for an exact midpoint', function () {
    // 25s sits exactly between the 20s and 30s samples; earlier wins.
    expect(locate('2023-10-31T21:00:25Z'))->toBe([51.2, -0.2]);
});

it('returns the first and last samples at the stream bounds', function () {
    expect(locate('2023-10-31T21:00:00Z'))->toBe([51.0, -0.0])
        ->and(locate('2023-10-31T21:00:40Z'))->toBe([51.4, -0.4]);
});

it('returns null for a photo taken before the activity started', function () {
    expect(locate('2023-10-31T20:59:59Z'))->toBeNull();
});

it('returns null for a photo taken after the activity ended', function () {
    expect(locate('2023-10-31T21:00:41Z'))->toBeNull();
});

it('returns null for an empty stream', function () {
    expect(locate('2023-10-31T21:00:20Z', [], []))->toBeNull();
});

it('handles a single-point stream without breaking the search', function () {
    expect(locate('2023-10-31T21:00:00Z', [0], [[51.5, -0.5]]))->toBe([51.5, -0.5])
        ->and(locate('2023-10-31T21:00:05Z', [0], [[51.5, -0.5]]))->toBeNull();
});

it('returns null when the latlng stream is shorter than the time stream', function () {
    expect(locate('2023-10-31T21:00:40Z', [0, 10, 20, 30, 40], [[51.0, -0.0]]))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=LocatePhotoOnRouteTest`
Expected: FAIL with `Class "App\Actions\LocatePhotoOnRoute" not found`

- [ ] **Step 3: Write minimal implementation**

Create `app/Actions/LocatePhotoOnRoute.php`:

```php
<?php

namespace App\Actions;

use Carbon\CarbonImmutable;

/**
 * Resolve where along a route a photo was taken, by matching its capture time
 * against an activity's GPS stream.
 *
 * Strava photos carry no coordinates and their EXIF is stripped, so position is
 * inferred: the photo's offset from the activity start is looked up in the
 * `time` stream, and the matching `latlng` sample is returned. Pure maths with
 * no HTTP or database access.
 */
class LocatePhotoOnRoute
{
    /**
     * The coordinate for a photo, or null when it cannot be placed on the route.
     *
     * @param  array<int, int>  $timeStream  Elapsed seconds from the activity start, ascending.
     * @param  array<int, array{0: float, 1: float}>  $latlngStream  Points as [lat, lng], parallel to $timeStream.
     * @return array{0: float, 1: float}|null The point as [lat, lng].
     */
    public function __invoke(
        CarbonImmutable $capturedAt,
        CarbonImmutable $activityStart,
        array $timeStream,
        array $latlngStream,
    ): ?array {
        if ($timeStream === [] || $latlngStream === []) {
            return null;
        }

        $offset = $capturedAt->getTimestamp() - $activityStart->getTimestamp();
        $last = count($timeStream) - 1;

        if ($offset < $timeStream[0] || $offset > $timeStream[$last]) {
            return null;
        }

        return $latlngStream[$this->nearestIndex($timeStream, $offset)] ?? null;
    }

    /**
     * The index of the stream sample closest to the given offset. Ties resolve to
     * the earlier sample, so the result is deterministic.
     *
     * @param  array<int, int>  $timeStream
     */
    private function nearestIndex(array $timeStream, int $offset): int
    {
        $low = 0;
        $high = count($timeStream) - 1;

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);

            if ($timeStream[$mid] < $offset) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        if ($low > 0 && ($offset - $timeStream[$low - 1]) <= ($timeStream[$low] - $offset)) {
            return $low - 1;
        }

        return $low;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=LocatePhotoOnRouteTest`
Expected: PASS (10 tests)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/LocatePhotoOnRoute.php tests/Unit/LocatePhotoOnRouteTest.php
git commit -m "feat: add LocatePhotoOnRoute to interpolate photo position from a GPS stream"
```

---

### Task 3: Shared test fixtures

Four test files across this plan need the same two fixtures. This task puts them
in one place.

**Context that overrides the original plan:** `tests/Feature/StravaPhotosTest.php`
already exists (added 4 July, commit 5e7e0371) with 6 tests, including the three
this task originally planned to write as characterization tests. That safety net
for Tasks 4 and 6 is already in place, so those three tests were dropped as
duplicates. What remains is fixture consolidation only.

That file already defines a global `fakeJpeg(int $width = 800, int $height = 600): string`
helper. Move it to `tests/Pest.php` rather than adding a second near-identical
JPEG fixture.

**Files:**
- Modify: `tests/Pest.php` (add the shared fixtures)
- Modify: `tests/Feature/StravaPhotosTest.php:11-21` (remove the moved helper)

**Interfaces:**
- Consumes: nothing.
- Produces: `fakeJpeg(int $width = 800, int $height = 600): string` and
  `stravaPhotoPayload(string $uniqueId, string $createdAt): array` in
  `tests/Pest.php`, used by Tasks 5, 6, 7, 8 and 9.

- [ ] **Step 1: Move fakeJpeg into tests/Pest.php and add the payload fixture**

Cut the `fakeJpeg()` function and its docblock from
`tests/Feature/StravaPhotosTest.php` (lines 11-21) and append it to
`tests/Pest.php`, unchanged, along with a new fixture:

```php
/** A real JPEG of the given size, so the media library can process it. */
function fakeJpeg(int $width = 800, int $height = 600): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

/**
 * A Strava photo payload, shaped like the real API response.
 *
 * @return array<string, mixed>
 */
function stravaPhotoPayload(string $uniqueId, string $createdAt): array
{
    return [
        'unique_id' => $uniqueId,
        'created_at' => $createdAt,
        'source' => 1,
        'urls' => ['2048' => 'https://dgtzuqphqg23d.cloudfront.net/'.$uniqueId.'-1152x2048.jpg'],
        'sizes' => ['2048' => [1152, 2048]],
    ];
}
```

Leave the Pest scaffold's default `function something()` stub alone.

- [ ] **Step 2: Run the existing suite to prove the move broke nothing**

Run: `php artisan test --compact --filter="StravaPhotosTest|PhotoGrid"`
Expected: PASS. `StravaPhotosTest`'s 6 existing tests must still pass, now
resolving `fakeJpeg()` from `tests/Pest.php`. A "cannot redeclare fakeJpeg"
error means the original was not fully removed from the test file.

- [ ] **Step 3: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Pest.php tests/Feature/StravaPhotosTest.php
git commit -m "test: move shared strava fixtures into tests/Pest.php"
```

---

### Task 4: Extract FetchStravaActivitySummaries

Both `StravaPhotos` and the new backfill command need `start_date` keyed by Strava id. Extract the paging loop now so it lives in one place, per the spec.

**Files:**
- Create: `app/Actions/FetchStravaActivitySummaries.php`
- Modify: `app/Console/Commands/Sync/StravaPhotos.php:59-113` (`resolveTargets()`)
- Test: `tests/Feature/FetchStravaActivitySummariesTest.php`

**Interfaces:**
- Consumes: `Strava::activitiesPage()` (existing).
- Produces: `FetchStravaActivitySummaries::__invoke(): ?array` returning `array<string, array{start_date: string, total_photo_count: int}>` keyed by Strava activity id as a string, or `null` on request failure.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/FetchStravaActivitySummariesTest.php`:

```php
<?php

use App\Actions\FetchStravaActivitySummaries;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.strava.client_id' => 'cid',
        'services.strava.client_secret' => 'secret',
        'services.strava.refresh_token' => 'refresh',
    ]);
    Cache::flush();
});

it('pages until exhausted and keys summaries by string id', function () {
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/athlete/activities*' => Http::sequence()
            ->push([['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 2]])
            ->push([['id' => 200, 'start_date' => '2023-11-01T08:00:00Z', 'total_photo_count' => 0]])
            ->push([]),
    ]);

    $summaries = app(FetchStravaActivitySummaries::class)();

    expect($summaries)->toHaveKeys(['100', '200'])
        ->and($summaries['100']['start_date'])->toBe('2023-10-31T21:00:00Z')
        ->and($summaries['100']['total_photo_count'])->toBe(2)
        ->and($summaries['200']['total_photo_count'])->toBe(0);
});

it('returns null when a page request fails', function () {
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/athlete/activities*' => Http::response([], 500),
    ]);

    expect(app(FetchStravaActivitySummaries::class)())->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FetchStravaActivitySummariesTest`
Expected: FAIL with `Class "App\Actions\FetchStravaActivitySummaries" not found`

- [ ] **Step 3: Write the action**

Create `app/Actions/FetchStravaActivitySummaries.php`:

```php
<?php

namespace App\Actions;

use App\Services\Strava;

/**
 * Page the athlete's full activity list once and keep only the fields the photo
 * commands need, keyed by Strava activity id.
 *
 * Both `strava:photos` and `strava:photo-locations` need each activity's UTC
 * start to place photos on the route. Fetching it per activity would cost one
 * request each against a 95-per-15-minute limit; paging the list costs roughly
 * one request per 200 activities.
 */
class FetchStravaActivitySummaries
{
    private const PER_PAGE = 200;

    public function __construct(private Strava $strava) {}

    /**
     * @return array<string, array{start_date: string, total_photo_count: int}>|null Null on a request failure.
     */
    public function __invoke(): ?array
    {
        $summaries = [];
        $page = 1;

        while (true) {
            $batch = $this->strava->activitiesPage($page, self::PER_PAGE);

            if ($batch === null) {
                return null;
            }

            if ($batch === []) {
                return $summaries;
            }

            foreach ($batch as $summary) {
                $summaries[(string) $summary['id']] = [
                    'start_date' => $summary['start_date'] ?? '',
                    'total_photo_count' => (int) ($summary['total_photo_count'] ?? 0),
                ];
            }

            $page++;
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=FetchStravaActivitySummariesTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Rewrite StravaPhotos to use the action**

In `app/Console/Commands/Sync/StravaPhotos.php`, replace the `PER_PAGE` constant and the whole `resolveTargets()` method. Delete `private const PER_PAGE = 200;` and the `use Illuminate\Support\Collection;` import stays.

Change the `handle()` signature to inject the action:

```php
    public function handle(Strava $strava, SyncStravaPhotos $sync, FetchStravaActivitySummaries $summaries): int
    {
        if (! $strava->token()) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        $targets = $this->resolveTargets($summaries);

        if ($targets === null) {
            return self::FAILURE;
        }

        if ($targets->isEmpty()) {
            $this->info('No activities with photos to backfill.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $targets = $targets->take($limit);
        }

        $this->info("Found {$targets->count()} activities with photos to fetch.");

        return $this->fetchPhotos($strava, $sync, $targets);
    }
```

Replace `resolveTargets()` with:

```php
    /**
     * The local activities that have photos on Strava but (unless forced) no
     * stored photo media yet, each paired with the activity's UTC start.
     *
     * @return Collection<int, array{activity: Activity, start: CarbonImmutable}>|null Null on a request failure.
     */
    private function resolveTargets(FetchStravaActivitySummaries $summaries): ?Collection
    {
        $remote = $summaries();

        if ($remote === null) {
            $this->error('Strava request failed while listing activities.');

            return null;
        }

        $ours = Activity::query()
            ->where('source', 'strava')
            ->whereNotNull('source_id')
            ->with('media')
            ->get()
            ->keyBy('source_id');

        $force = (bool) $this->option('force');
        $targets = collect();

        foreach ($remote as $sourceId => $summary) {
            if ($summary['total_photo_count'] < 1) {
                continue;
            }

            $activity = $ours->get($sourceId);

            if (! $activity) {
                continue;
            }

            if (! $force && $activity->getMedia('cover')->isNotEmpty()) {
                continue;
            }

            $targets->push([
                'activity' => $activity,
                'start' => CarbonImmutable::parse($summary['start_date']),
            ]);
        }

        return $targets;
    }
```

Update `fetchPhotos()` to unpack the new shape. Replace its signature and loop body:

```php
    /**
     * Fetch and store photos for each target activity, pausing when the Strava
     * rate-limit window fills up.
     *
     * @param  Collection<int, array{activity: Activity, start: CarbonImmutable}>  $targets
     */
    private function fetchPhotos(Strava $strava, SyncStravaPhotos $sync, Collection $targets): int
    {
        $stored = 0;
        $requestsInWindow = 0;
        $windowStart = time();

        foreach ($targets as $target) {
            $activity = $target['activity'];

            if ($requestsInWindow >= self::RATE_LIMIT) {
                $wait = self::RATE_WINDOW - (time() - $windowStart);

                if ($wait > 0) {
                    $this->info("Rate limit reached. Waiting {$wait}s...");
                    sleep($wait);
                }

                $requestsInWindow = 0;
                $windowStart = time();
            }

            $photos = $strava->activityPhotos($activity->source_id);
            $requestsInWindow++;

            if ($photos === null) {
                $this->warn("Failed to fetch photos for {$activity->source_id}");

                continue;
            }

            $count = $sync($activity, $photos);
            $stored += $count;

            $this->info("[{$stored}] {$activity->name} - {$count} photo(s)");
        }

        $this->info("Done. Stored {$stored} photo(s).");

        return self::SUCCESS;
    }
```

Add the imports at the top of the file:

```php
use App\Actions\FetchStravaActivitySummaries;
use Carbon\CarbonImmutable;
```

- [ ] **Step 6: Run the existing StravaPhotos tests to prove nothing broke**

Run: `php artisan test --compact --filter="StravaPhotosTest|FetchStravaActivitySummariesTest"`
Expected: PASS. The 6 pre-existing StravaPhotosTest tests must still pass unchanged.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/FetchStravaActivitySummaries.php app/Console/Commands/Sync/StravaPhotos.php tests/Feature/FetchStravaActivitySummariesTest.php
git commit -m "refactor: extract Strava activity summary paging into a shared action"
```

---

### Task 5: SyncStravaPhotos writes coordinates

**Files:**
- Modify: `app/Actions/SyncStravaPhotos.php`
- Test: `tests/Feature/SyncStravaPhotosTest.php`

**Interfaces:**
- Consumes: `LocatePhotoOnRoute` (Task 2).
- Produces: `SyncStravaPhotos::__invoke(Activity $activity, array $photos, ?array $streams = null, ?CarbonImmutable $activityStart = null): int`. The `$streams` argument is the raw response from `Strava::activityStreams()`. Coordinates land in custom properties as `latitude` and `longitude`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SyncStravaPhotosTest.php`:

```php
<?php

use App\Actions\SyncStravaPhotos;
use App\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');
    Http::fake(['*dgtzuqphqg23d.cloudfront.net*' => Http::response(fakeJpeg())]);
});

function syncStreams(): array
{
    return [
        'time' => ['data' => [0, 10, 20]],
        'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
    ];
}

it('stores a located photo with latitude and longitude custom properties', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    (new SyncStravaPhotos(new App\Actions\LocatePhotoOnRoute))(
        $activity,
        [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')],
        syncStreams(),
        CarbonImmutable::parse('2023-10-31T21:00:00Z'),
    );

    $media = $activity->refresh()->getFirstMedia('cover');

    expect($media->getCustomProperty('latitude'))->toBe(51.1)
        ->and($media->getCustomProperty('longitude'))->toBe(-0.1);
});

it('stores an out-of-window photo with no coordinate properties', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    // Taken an hour after the 20-second stream ended, like an Instagram upload.
    (new SyncStravaPhotos(new App\Actions\LocatePhotoOnRoute))(
        $activity,
        [stravaPhotoPayload('photo-a', '2023-10-31T22:00:00Z')],
        syncStreams(),
        CarbonImmutable::parse('2023-10-31T21:00:00Z'),
    );

    $media = $activity->refresh()->getFirstMedia('cover');

    expect($media)->not->toBeNull()
        ->and($media->hasCustomProperty('latitude'))->toBeFalse();
});

it('stores photos when no stream is supplied at all', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    $stored = (new SyncStravaPhotos(new App\Actions\LocatePhotoOnRoute))(
        $activity,
        [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')],
    );

    expect($stored)->toBe(1)
        ->and($activity->refresh()->getFirstMedia('cover')->hasCustomProperty('latitude'))->toBeFalse();
});

it('stores photos when the stream has no latlng key, as on an indoor activity', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    $stored = (new SyncStravaPhotos(new App\Actions\LocatePhotoOnRoute))(
        $activity,
        [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')],
        ['time' => ['data' => [0, 10, 20]], 'distance' => ['data' => [0, 5, 9]]],
        CarbonImmutable::parse('2023-10-31T21:00:00Z'),
    );

    expect($stored)->toBe(1)
        ->and($activity->refresh()->getFirstMedia('cover')->hasCustomProperty('latitude'))->toBeFalse();
});
```

`fakeJpeg()` and `stravaPhotoPayload()` come from `tests/Pest.php` (Task 3), so they need no redeclaring here.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SyncStravaPhotosTest`
Expected: FAIL. `SyncStravaPhotos::__construct()` takes no arguments yet, so `new SyncStravaPhotos(new LocatePhotoOnRoute)` errors.

- [ ] **Step 3: Write the implementation**

Replace `app/Actions/SyncStravaPhotos.php` entirely:

```php
<?php

namespace App\Actions;

use App\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Download an activity's Strava photos and store them as media: the first photo
 * becomes the single `cover`, the rest fill the `photos` gallery. Existing photo
 * media is cleared first so re-running is idempotent.
 *
 * When a GPS stream and the activity's UTC start are supplied, each photo is
 * located on the route and its coordinate stored as custom properties. A photo
 * that cannot be located is still stored, just without coordinates.
 */
class SyncStravaPhotos
{
    public function __construct(private LocatePhotoOnRoute $locate) {}

    /**
     * @param  array<int, array<string, mixed>>  $photos  The raw Strava photos payload.
     * @param  array<string, array{data: array<int, mixed>}>|null  $streams  The raw Strava streams payload.
     * @return int The number of photos stored.
     */
    public function __invoke(
        Activity $activity,
        array $photos,
        ?array $streams = null,
        ?CarbonImmutable $activityStart = null,
    ): int {
        $activity->clearMediaCollection('cover');
        $activity->clearMediaCollection('photos');

        $timeStream = $streams['time']['data'] ?? [];
        $latlngStream = $streams['latlng']['data'] ?? [];
        $canLocate = $activityStart !== null && $timeStream !== [] && $latlngStream !== [];

        $stored = 0;

        foreach ($photos as $photo) {
            $url = $photo['urls']['2048'] ?? $photo['urls']['600'] ?? null;

            if (! $url) {
                continue;
            }

            $response = Http::get($url);

            if ($response->failed()) {
                continue;
            }

            $media = $activity->addMediaFromString($response->body())
                ->usingFileName(($photo['unique_id'] ?? Str::uuid()).'.jpg');

            $coordinate = $canLocate
                ? $this->coordinateFor($photo, $activityStart, $timeStream, $latlngStream)
                : null;

            if ($coordinate !== null) {
                $media->withCustomProperties([
                    'latitude' => $coordinate[0],
                    'longitude' => $coordinate[1],
                ]);
            }

            $media->toMediaCollection($stored === 0 ? 'cover' : 'photos');

            $stored++;
        }

        return $stored;
    }

    /**
     * The route coordinate for a single photo, or null when it has no capture
     * time or falls outside the activity's stream.
     *
     * @param  array<string, mixed>  $photo
     * @param  array<int, int>  $timeStream
     * @param  array<int, array{0: float, 1: float}>  $latlngStream
     * @return array{0: float, 1: float}|null
     */
    private function coordinateFor(
        array $photo,
        CarbonImmutable $activityStart,
        array $timeStream,
        array $latlngStream,
    ): ?array {
        $capturedAt = $photo['created_at'] ?? null;

        if (! is_string($capturedAt) || $capturedAt === '') {
            return null;
        }

        return ($this->locate)(
            CarbonImmutable::parse($capturedAt),
            $activityStart,
            $timeStream,
            $latlngStream,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=SyncStravaPhotosTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Run the existing StravaPhotos tests to prove the default path still works**

Run: `php artisan test --compact --filter=StravaPhotosTest`
Expected: PASS. `SyncStravaPhotos` is resolved from the container in `StravaPhotos`, so the new constructor argument is auto-injected. Note two of these tests call `app(SyncStravaPhotos::class)($activity, $photos)` directly with 2 arguments, which the new optional parameters keep working.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/SyncStravaPhotos.php tests/Feature/SyncStravaPhotosTest.php
git commit -m "feat: store located photo coordinates as media custom properties"
```

---

### Task 6: StravaPhotos fetches streams, resiliently

The rule from the spec: a stream problem must never cost us a photo.

**Files:**
- Modify: `app/Console/Commands/Sync/StravaPhotos.php` (`fetchPhotos()`)
- Test: `tests/Feature/StravaPhotosTest.php` (append to the existing file, which already has 6 tests)

**Interfaces:**
- Consumes: `Strava::activityStreams()` (Task 1), `SyncStravaPhotos::__invoke()` 4-argument form (Task 5), `fakeJpeg()` and `stravaPhotoPayload()` from `tests/Pest.php` (Task 3).
- Produces: nothing new.

The existing file's `beforeEach` already sets the Strava config, flushes the
cache and fakes the `public` disk, so appended tests inherit all of it.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/StravaPhotosTest.php`. First the local helper, which
only this file needs, placed after the existing `beforeEach` block:

```php
/**
 * Fake the whole Strava surface: token, one page of summaries, per-activity
 * photos, and the CloudFront image download.
 *
 * @param  array<int, array<string, mixed>>  $summaries
 * @param  array<string, array<int, array<string, mixed>>>  $photosById
 * @param  array<string, mixed>  $extra
 */
function fakeStravaPhotos(array $summaries, array $photosById, array $extra = []): void
{
    $responses = [
        '*/oauth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        '*dgtzuqphqg23d.cloudfront.net*' => Http::response(fakeJpeg()),
        '*/athlete/activities*' => Http::sequence()
            ->push($summaries)
            ->push([]),
    ];

    foreach ($photosById as $id => $photos) {
        $responses["*/activities/{$id}/photos*"] = Http::response($photos);
    }

    Http::fake(array_merge($responses, $extra));
}
```

Then the three tests:

```php
it('stores photos with coordinates interpolated from the stream', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    fakeStravaPhotos(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        ['100' => [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')]],
        ['*/streams*' => Http::response([
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ])],
    );

    $this->artisan('strava:photos')->assertSuccessful();

    $media = $activity->refresh()->getFirstMedia('cover');

    expect($media->getCustomProperty('latitude'))->toBe(51.1)
        ->and($media->getCustomProperty('longitude'))->toBe(-0.1);
});

it('still stores the photo when the streams request fails', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    fakeStravaPhotos(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        ['100' => [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')]],
        ['*/streams*' => Http::response([], 500)],
    );

    $this->artisan('strava:photos')->assertSuccessful();

    $media = $activity->refresh()->getFirstMedia('cover');

    // The photo survives a stream failure; only the marker is lost.
    expect($media)->not->toBeNull()
        ->and($media->hasCustomProperty('latitude'))->toBeFalse();
});

it('still stores the photo for an indoor activity with no latlng stream', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    fakeStravaPhotos(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        ['100' => [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')]],
        ['*/streams*' => Http::response([
            'time' => ['data' => [0, 10, 20]],
            'distance' => ['data' => [0, 5, 9]],
        ])],
    );

    $this->artisan('strava:photos')->assertSuccessful();

    expect($activity->refresh()->getFirstMedia('cover'))->not->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=StravaPhotosTest`
Expected: the first new test FAILS (`getCustomProperty('latitude')` is null, since the command never fetches streams). The other two may pass incidentally; they are regression guards for Step 3.

- [ ] **Step 3: Write the implementation**

In `app/Console/Commands/Sync/StravaPhotos.php`, inside `fetchPhotos()`, replace the block from `$photos = $strava->activityPhotos(...)` through `$count = $sync($activity, $photos);` with:

```php
            $photos = $strava->activityPhotos($activity->source_id);
            $requestsInWindow++;

            if ($photos === null) {
                $this->warn("Failed to fetch photos for {$activity->source_id}");

                continue;
            }

            $streams = $strava->activityStreams($activity->source_id);
            $requestsInWindow++;

            if ($streams === null) {
                $this->warn("Failed to fetch streams for {$activity->source_id}, storing photos without map positions.");
            }

            $count = $sync($activity, $photos, $streams, $target['start']);
```

The stream failure warns and carries on. It must not `continue`, or a Strava hiccup would cost us the photo itself.

Bump the rate-limit guard, since each activity now costs two requests rather than one. Change the check at the top of the loop:

```php
            if ($requestsInWindow >= self::RATE_LIMIT - 1) {
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=StravaPhotosTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/Sync/StravaPhotos.php tests/Feature/StravaPhotosTest.php
git commit -m "feat: locate strava photos on the route during photo sync"
```

---

### Task 7: strava:photo-locations backfill command

Attaches coordinates to the 134 photos already downloaded, without re-fetching a single image. Also the answer to the spec's "interpolating at sync is lossy" trade-off: re-deriving is cheap.

**Files:**
- Create: `app/Console/Commands/Sync/StravaPhotoLocations.php`
- Test: `tests/Feature/StravaPhotoLocationsTest.php`

**Interfaces:**
- Consumes: `FetchStravaActivitySummaries` (Task 4), `Strava::activityStreams()` (Task 1), `LocatePhotoOnRoute` (Task 2).
- Produces: the `strava:photo-locations` command with a `--force` option.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/StravaPhotoLocationsTest.php`:

```php
<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'services.strava.client_id' => 'cid',
        'services.strava.client_secret' => 'secret',
        'services.strava.refresh_token' => 'refresh',
        'queue.default' => 'sync',
    ]);
    Cache::flush();
    Storage::fake('public');
});

/** An activity with one already-downloaded photo, as the backfill will find it. */
function activityWithStoredPhoto(string $sourceId, string $capturedAt): Activity
{
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => $sourceId]);
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('photo-a.jpg')
        ->withCustomProperties(['captured_at' => $capturedAt])
        ->toMediaCollection('cover');

    return $activity;
}

function fakeStravaLocations(array $summaries, array $streams): void
{
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/athlete/activities*' => Http::sequence()->push($summaries)->push([]),
        '*/streams*' => Http::response($streams),
    ]);
}

it('writes coordinates onto existing media without re-downloading the image', function () {
    $activity = activityWithStoredPhoto('100', '2023-10-31T21:00:10Z');

    fakeStravaLocations(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        [
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ],
    );

    $this->artisan('strava:photo-locations')->assertSuccessful();

    $media = $activity->refresh()->getFirstMedia('cover');

    expect($media->getCustomProperty('latitude'))->toBe(51.1)
        ->and($media->getCustomProperty('longitude'))->toBe(-0.1);

    // The whole point of this command: no image bytes are fetched again.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'cloudfront.net'));
});

it('leaves an out-of-window photo without coordinates', function () {
    $activity = activityWithStoredPhoto('100', '2023-10-31T22:00:00Z');

    fakeStravaLocations(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        [
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ],
    );

    $this->artisan('strava:photo-locations')->assertSuccessful();

    expect($activity->refresh()->getFirstMedia('cover')->hasCustomProperty('latitude'))->toBeFalse();
});

it('skips media that already has coordinates unless forced', function () {
    $activity = activityWithStoredPhoto('100', '2023-10-31T21:00:10Z');
    $media = $activity->getFirstMedia('cover');
    $media->setCustomProperty('latitude', 1.0);
    $media->setCustomProperty('longitude', 2.0);
    $media->save();

    fakeStravaLocations(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        [
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ],
    );

    $this->artisan('strava:photo-locations')
        ->expectsOutputToContain('No photos to locate.')
        ->assertSuccessful();

    expect($activity->refresh()->getFirstMedia('cover')->getCustomProperty('latitude'))->toBe(1.0);

    // Forcing re-derives it from the stream.
    $this->artisan('strava:photo-locations', ['--force' => true])->assertSuccessful();

    expect($activity->refresh()->getFirstMedia('cover')->getCustomProperty('latitude'))->toBe(51.1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=StravaPhotoLocationsTest`
Expected: FAIL with `The command "strava:photo-locations" does not exist.`

- [ ] **Step 3: Note the captured_at dependency and extend the sync**

The backfill needs each photo's capture time, which lives in the Strava payload, not on the stored file. Store it at download time so the backfill can re-derive positions without touching the photos endpoint.

In `app/Actions/SyncStravaPhotos.php`, always record the capture time. Replace the `$media` assignment and the coordinate block inside the loop with:

```php
            $media = $activity->addMediaFromString($response->body())
                ->usingFileName(($photo['unique_id'] ?? Str::uuid()).'.jpg');

            $properties = [];
            $capturedAt = $photo['created_at'] ?? null;

            if (is_string($capturedAt) && $capturedAt !== '') {
                $properties['captured_at'] = $capturedAt;
            }

            $coordinate = $canLocate
                ? $this->coordinateFor($photo, $activityStart, $timeStream, $latlngStream)
                : null;

            if ($coordinate !== null) {
                $properties['latitude'] = $coordinate[0];
                $properties['longitude'] = $coordinate[1];
            }

            if ($properties !== []) {
                $media->withCustomProperties($properties);
            }

            $media->toMediaCollection($stored === 0 ? 'cover' : 'photos');
```

- [ ] **Step 4: Write the command**

Create `app/Console/Commands/Sync/StravaPhotoLocations.php`:

```php
<?php

namespace App\Console\Commands\Sync;

use App\Actions\FetchStravaActivitySummaries;
use App\Actions\LocatePhotoOnRoute;
use App\Models\Activity;
use App\Services\Strava;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Backfill route coordinates onto Strava photos that are already downloaded.
 *
 * Interpolation happens at sync time, so changing the maths would otherwise mean
 * re-downloading every image. This command re-derives positions from the GPS
 * stream alone and never fetches image bytes.
 */
#[Signature('strava:photo-locations {--limit=0 : Max activities to locate photos for (0 = all)} {--force : Re-derive coordinates for photos that already have them}')]
#[Description('Backfill map coordinates onto already-downloaded Strava photos')]
class StravaPhotoLocations extends Command
{
    private const RATE_LIMIT = 95;

    private const RATE_WINDOW = 900;

    public function handle(Strava $strava, FetchStravaActivitySummaries $summaries, LocatePhotoOnRoute $locate): int
    {
        if (! $strava->token()) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        $remote = $summaries();

        if ($remote === null) {
            $this->error('Strava request failed while listing activities.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        $targets = Activity::query()
            ->where('source', 'strava')
            ->whereNotNull('source_id')
            ->with('media')
            ->get()
            ->filter(fn (Activity $activity): bool => $this->photosToLocate($activity, $force)->isNotEmpty())
            ->filter(fn (Activity $activity): bool => isset($remote[$activity->source_id]))
            ->values();

        if ($targets->isEmpty()) {
            $this->info('No photos to locate.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $targets = $targets->take($limit);
        }

        $this->info("Found {$targets->count()} activities with photos to locate.");

        return $this->locatePhotos($strava, $locate, $targets, $remote, $force);
    }

    /**
     * The activity's stored photos that still need a coordinate.
     *
     * @return \Illuminate\Support\Collection<int, Media>
     */
    private function photosToLocate(Activity $activity, bool $force): \Illuminate\Support\Collection
    {
        return $activity->getMedia('cover')
            ->merge($activity->getMedia('photos'))
            ->filter(fn (Media $media): bool => $media->hasCustomProperty('captured_at'))
            ->filter(fn (Media $media): bool => $force || ! $media->hasCustomProperty('latitude'))
            ->values();
    }

    /**
     * Fetch each activity's stream and write coordinates onto its photos,
     * pausing when the Strava rate-limit window fills up.
     *
     * @param  \Illuminate\Support\Collection<int, Activity>  $targets
     * @param  array<string, array{start_date: string, total_photo_count: int}>  $remote
     */
    private function locatePhotos(
        Strava $strava,
        LocatePhotoOnRoute $locate,
        \Illuminate\Support\Collection $targets,
        array $remote,
        bool $force,
    ): int {
        $located = 0;
        $requestsInWindow = 0;
        $windowStart = time();

        foreach ($targets as $activity) {
            if ($requestsInWindow >= self::RATE_LIMIT) {
                $wait = self::RATE_WINDOW - (time() - $windowStart);

                if ($wait > 0) {
                    $this->info("Rate limit reached. Waiting {$wait}s...");
                    sleep($wait);
                }

                $requestsInWindow = 0;
                $windowStart = time();
            }

            $streams = $strava->activityStreams($activity->source_id);
            $requestsInWindow++;

            $timeStream = $streams['time']['data'] ?? [];
            $latlngStream = $streams['latlng']['data'] ?? [];

            if ($timeStream === [] || $latlngStream === []) {
                $this->warn("No usable stream for {$activity->source_id}, skipping.");

                continue;
            }

            $start = CarbonImmutable::parse($remote[$activity->source_id]['start_date']);
            $count = 0;

            foreach ($this->photosToLocate($activity, $force) as $media) {
                $coordinate = $locate(
                    CarbonImmutable::parse($media->getCustomProperty('captured_at')),
                    $start,
                    $timeStream,
                    $latlngStream,
                );

                if ($coordinate === null) {
                    continue;
                }

                $media->setCustomProperty('latitude', $coordinate[0]);
                $media->setCustomProperty('longitude', $coordinate[1]);
                $media->save();

                $count++;
            }

            $located += $count;

            $this->info("[{$located}] {$activity->name} - {$count} photo(s) located");
        }

        $this->info("Done. Located {$located} photo(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=StravaPhotoLocationsTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Run the full Strava suite**

Run: `php artisan test --compact --filter="Strava|LocatePhoto|SyncStravaPhotos"`
Expected: PASS. The `captured_at` change in Step 3 must not have broken Task 5's tests.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/Sync/StravaPhotoLocations.php app/Actions/SyncStravaPhotos.php tests/Feature/StravaPhotoLocationsTest.php
git commit -m "feat: add strava:photo-locations to backfill coordinates without re-downloading"
```

---

### Task 8: Expose coordinates through galleryPhotos()

**Files:**
- Modify: `app/Models/Concerns/HasAttachments.php:43-54`
- Test: `tests/Feature/GalleryPhotoCoordinatesTest.php`

**Interfaces:**
- Consumes: media custom properties written in Tasks 5 and 7.
- Produces: each entry in the `photos` Inertia prop gains `latitude` and `longitude` (`float|null`), alongside the existing `src`, `srcset`, `full`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/GalleryPhotoCoordinatesTest.php`:

```php
<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');
});

it('exposes latitude and longitude for a located photo', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('located.jpg')
        ->withCustomProperties(['latitude' => 51.1, 'longitude' => -0.1])
        ->toMediaCollection('cover');

    $photos = $activity->refresh()->galleryPhotos();

    expect($photos[0]['latitude'])->toBe(51.1)
        ->and($photos[0]['longitude'])->toBe(-0.1);
});

it('exposes null coordinates for an unlocated photo', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);
    $activity->addMediaFromString(fakeJpeg())->usingFileName('plain.jpg')->toMediaCollection('cover');

    $photos = $activity->refresh()->galleryPhotos();

    expect($photos[0]['latitude'])->toBeNull()
        ->and($photos[0]['longitude'])->toBeNull();
});

it('keeps cover first so photo indexes stay stable for the lightbox', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);
    $activity->addMediaFromString(fakeJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('gallery.jpg')
        ->withCustomProperties(['latitude' => 51.2, 'longitude' => -0.2])
        ->toMediaCollection('photos');

    $photos = $activity->refresh()->galleryPhotos();

    // The unlocated cover holds index 0; the located gallery photo is index 1.
    expect($photos[0]['latitude'])->toBeNull()
        ->and($photos[1]['latitude'])->toBe(51.2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GalleryPhotoCoordinatesTest`
Expected: FAIL with `Undefined array key "latitude"`

- [ ] **Step 3: Write the implementation**

In `app/Models/Concerns/HasAttachments.php`, replace `galleryPhotos()`:

```php
    /**
     * The entry's photos in display order (cover first, then the gallery), each
     * with the optimised card source, its responsive srcset, the full-size
     * original for the lightbox, and the route coordinate where known.
     *
     * @return array<int, array{src: string, srcset: ?string, full: string, latitude: ?float, longitude: ?float}>
     */
    public function galleryPhotos(): array
    {
        return $this->getMedia('cover')
            ->merge($this->getMedia('photos'))
            ->map(fn (Media $media): array => [
                'src' => $media->getUrl('card'),
                'srcset' => $media->getSrcset('card') ?: null,
                'full' => $media->getUrl(),
                'latitude' => $media->getCustomProperty('latitude'),
                'longitude' => $media->getCustomProperty('longitude'),
            ])
            ->values()
            ->all();
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=GalleryPhotoCoordinatesTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Run the wider suite, since the trait is shared by notes and events**

Run: `php artisan test --compact --filter="Note|Event|Article|EntryView"`
Expected: PASS. The extra keys are additive, so nothing should break.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Concerns/HasAttachments.php tests/Feature/GalleryPhotoCoordinatesTest.php
git commit -m "feat: expose photo coordinates on the gallery photo payload"
```

---

### Task 9: Photo markers on the map

**Files:**
- Create: `resources/js/Components/Maps/PhotoMarker.vue`
- Modify: `resources/js/Components/Maps/EntryMap.vue`
- Modify: `resources/js/Components/Entry/ActivityMedia.vue`
- Test: `tests/Browser/ActivityPhotoMarkersTest.php`

**Interfaces:**
- Consumes: the `latitude`/`longitude` keys from Task 8.
- Produces: `PhotoMarker.vue` (props `photo: Object`, `label: String`; emits `select`), `EntryMap` prop `photos: Array` and emit `open-photo` carrying the photo's index in the original array.

- [ ] **Step 1: Write the failing browser test**

Create `tests/Browser/ActivityPhotoMarkersTest.php`:

```php
<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

/** A solid-colour JPEG, so the media pipeline generates a card conversion. */
function markerJpeg(): string
{
    $image = imagecreatetruecolor(400, 300);
    imagefill($image, 0, 0, imagecolorallocate($image, 120, 120, 120));
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

/** A short polyline near Aylesby, so markers have a route to sit on. */
const MARKER_POLYLINE = 'ohreIzatO}@}A_@k@';

it('renders a marker for each located photo and none for unlocated ones', function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');

    $activity = Activity::factory()->create([
        'type' => 'walk',
        'occurred_at' => '2026-07-12 12:00:00',
        'meta' => ['polyline' => MARKER_POLYLINE],
    ]);

    // Index 0 is unlocated, index 1 is located: the marker must carry index 1.
    $activity->addMediaFromString(markerJpeg())->usingFileName('unlocated.jpg')->toMediaCollection('cover');
    $activity->addMediaFromString(markerJpeg())
        ->usingFileName('located.jpg')
        ->withCustomProperties(['latitude' => 53.5675, 'longitude' => -0.1425])
        ->toMediaCollection('photos');

    $page = visit($activity->url());

    // Assert on rendered DOM, not text: latitude/longitude also live in the
    // Inertia props JSON, so a text assertion would pass with zero markers.
    $page->assertScript("document.querySelectorAll('[data-testid=\"photo-marker\"]').length", 1);
});

it('opens the lightbox at the right photo when a marker is clicked', function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');

    $activity = Activity::factory()->create([
        'type' => 'walk',
        'occurred_at' => '2026-07-12 12:00:00',
        'meta' => ['polyline' => MARKER_POLYLINE],
    ]);

    $activity->addMediaFromString(markerJpeg())->usingFileName('unlocated.jpg')->toMediaCollection('cover');
    $activity->addMediaFromString(markerJpeg())
        ->usingFileName('located.jpg')
        ->withCustomProperties(['latitude' => 53.5675, 'longitude' => -0.1425])
        ->toMediaCollection('photos');

    $page = visit($activity->url());

    $page->click('[data-testid="photo-marker"]');

    // The Lightbox dialog is a real element, so its presence proves the click landed.
    $page->assertScript("document.querySelector('[role=\"dialog\"]') !== null", true);
});
```

`Activity::url()` comes from `HasTimelineEntry` and resolves to
`/Y/m/d/{slug}`. `meta.polyline` is the key `ActivityDetail.vue:20` reads
(`props.entry.meta?.polyline`), and `Activity::card()` passes it through at
`app/Models/Activity.php:76`. The factory has no route-bearing state, so setting
`meta` by hand is correct here.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ActivityPhotoMarkersTest`
Expected: FAIL. Expected 1 marker, got 0.

- [ ] **Step 3: Write PhotoMarker.vue**

Create `resources/js/Components/Maps/PhotoMarker.vue`:

```vue
<script setup>
defineProps({
    photo: { type: Object, required: true },
    label: { type: String, required: true },
});

defineEmits(['select']);
</script>

<template>
    <button
        type="button"
        data-testid="photo-marker"
        :aria-label="label"
        class="block size-11 overflow-hidden rounded-full border-2 border-neutral-0 shadow-md ring-2 ring-activity transition-transform hover:scale-110 focus-visible:scale-110 focus-visible:outline-none focus-visible:ring-4"
        @click="$emit('select')"
    >
        <img
            :src="photo.src"
            :srcset="photo.srcset || undefined"
            sizes="44px"
            alt=""
            class="size-full object-cover"
        >
    </button>
</template>
```

The button carries the label, so the image is decorative. `sizes="44px"` stops a 640px card image loading for a 44px circle.

- [ ] **Step 4: Wire markers into EntryMap.vue**

In `resources/js/Components/Maps/EntryMap.vue`, add `computed` to the vue import and import the marker:

```js
import { onMounted, onBeforeUnmount, ref, computed, watch } from 'vue';
import PhotoMarker from './PhotoMarker.vue';
```

Add the prop and emit below the existing `defineProps`:

```js
const props = defineProps({
    polyline: { type: String, required: true },
    color: { type: String, default: '#3858e9' },
    heightClass: { type: String, default: 'h-72 sm:h-96' },
    photos: { type: Array, default: () => [] },
});

const emit = defineEmits(['open-photo']);

// Photos that have a coordinate, each keeping its index in the ORIGINAL photos
// array. The Lightbox opens by that index, so mapping must happen before
// filtering: filtering first would renumber them and open the wrong photo.
const locatedPhotos = computed(() =>
    props.photos
        .map((photo, index) => ({ ...photo, index }))
        .filter((photo) => photo.latitude !== null && photo.longitude !== null),
);
```

Add the marker refs beside the existing `container` ref:

```js
const markerRefs = ref([]);
let markers = [];
```

Add this function beside `addRouteLayer`, inside `onMounted` after `map` is created (markers are DOM overlays rather than style layers, so unlike `addRouteLayer` they survive `setStyle` and need no re-add on theme change):

```js
    // Attach a MapLibre marker per located photo. Strava gives [lat, lng] and
    // maplibre wants [lng, lat], so the pair flips here and only here.
    function addPhotoMarkers() {
        locatedPhotos.value.forEach((photo, position) => {
            const element = markerRefs.value[position];

            if (!element) {
                return;
            }

            markers.push(
                new maplibregl.Marker({ element })
                    .setLngLat([photo.longitude, photo.latitude])
                    .addTo(map),
            );
        });
    }
```

Call it right after `map.addControl(...)`:

```js
    addPhotoMarkers();
```

Clean up in `onBeforeUnmount`, before `map?.remove()`:

```js
    markers.forEach((marker) => marker.remove());
    markers = [];
```

Add the marker elements to the template, inside the outer `<div class="relative">` and after the existing button. MapLibre moves each node into its own overlay on `addTo`, so this container is only their birthplace:

```vue
        <div class="hidden">
            <PhotoMarker
                v-for="(photo, position) in locatedPhotos"
                :key="photo.index"
                :ref="(el) => (markerRefs[position] = el?.$el)"
                :photo="photo"
                :label="`View photo ${photo.index + 1}`"
                @select="emit('open-photo', photo.index)"
            />
        </div>
```

- [ ] **Step 5: Pass photos through ActivityMedia.vue**

In `resources/js/Components/Entry/ActivityMedia.vue`, pass the photos down and forward the event into the existing `open` emit, so a marker click and a grid click reach the identical Lightbox call:

```vue
        <EntryMap
            v-if="polyline"
            :polyline="polyline"
            :photos="photos"
            :color="color"
            @open-photo="$emit('open', $event)"
        />
```

`ActivityDetail.vue` needs no change: it already passes `:photos="photos"` and wires `@open="lightboxIndex = $event"`.

- [ ] **Step 6: Run the browser test to verify it passes**

Run: `php artisan test --compact --filter=ActivityPhotoMarkersTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Verify in the real app**

Run `npm run build`, then load a real activity that has photos and check a marker sits on the route and opens the Lightbox. Confirm the marker is reachable by keyboard (Tab to it, Enter opens the Lightbox) and that it renders correctly in both light and dark mode.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Components/Maps/PhotoMarker.vue resources/js/Components/Maps/EntryMap.vue resources/js/Components/Entry/ActivityMedia.vue tests/Browser/ActivityPhotoMarkersTest.php
git commit -m "feat: show activity photos as markers on the route map"
```

---

### Task 10: Run the backfill

**Files:** none. This task runs the commands against live data.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS. Fix anything broken before touching live data.

- [ ] **Step 2: Locate photos on a few activities first**

Run: `php artisan strava:photo-locations --limit=5`
Expected: output naming up to 5 activities with a per-activity located count.

Existing photos predate the `captured_at` custom property from Task 7, so they will report "No photos to locate." If so, the images need re-downloading once to record capture times: run `php artisan strava:photos --force --limit=5` instead, then re-check.

- [ ] **Step 3: Check the result in the browser**

Load one of those activities and confirm the markers sit on the route in sensible places. A marker in the Atlantic off Africa means a lat/lng flip; a marker on the route but at the wrong point means an offset error.

- [ ] **Step 4: Run the full backfill**

Run: `php artisan strava:photo-locations`
Expected: roughly 103 activities processed, with one rate-limit pause of up to 15 minutes.

- [ ] **Step 5: Report the numbers**

Report how many of the 134 photos got coordinates and how many did not, with the likely reason for the misses (Instagram-sourced, added after the activity, or no GPS stream). Do not claim success without these numbers.

---

## Self-Review Notes

**Spec coverage:** `activityStreams` (Task 1), `LocatePhotoOnRoute` including tie-breaking and the coordinate-order trap (Task 2), shared summary paging (Task 4), custom properties on sync (Task 5), stream resilience and the indoor no-latlng case (Task 6), the backfill command with no image re-download (Task 7), `galleryPhotos()` (Task 8), `PhotoMarker` plus index preservation and the browser DOM assertion (Task 9).

**Deviation from the spec, worth flagging at review:** the spec did not account for the backfill needing each photo's capture time. Stored media has no record of it, and the whole point of the command is to avoid re-fetching. Task 7 Step 3 therefore adds a `captured_at` custom property at download time. The consequence, called out in Task 10 Step 2, is that the 134 existing photos must be re-downloaded once via `strava:photos --force` before the backfill can ever locate them. That weakens the "no re-download" benefit for the first run only; every subsequent re-derivation is free, which is what the trade-off was chosen for.

**Out of scope, per the spec:** clustering, `RouteThumb`/`RouteHeatmap` markers, map popups, locating note and event photos.
