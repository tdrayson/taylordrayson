# Activity stream charts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pull Strava altitude/speed/heart-rate/latlng streams onto activities and show three stacked profile charts under the route map, with one vertical cursor synced across the charts and a dot on the route, bidirectionally (pointer/touch).

**Architecture:** Strava streams are fetched aligned, downsampled by shared indices to absolute-time JSON columns on `activities` (mirroring the existing `heart_rate` column). A deferred Inertia prop delivers the series to `ActivityDetail.vue`, which renders `ActivityProfile.vue` (three Chart.js charts) and passes a shared reactive cursor to both the charts and `EntryMap.vue` (which gains a route dot + pointer scrub).

**Tech Stack:** PHP 8.4, Laravel 13, Pest 4, Inertia v3 + Vue 3, Chart.js 4, MapLibre GL, Strava API.

## Global Constraints

- PHP 8.4: explicit return types; constructor property promotion; curly braces on all control structures; PHPDoc over inline comments.
- Vue/JS: comment functions, computeds, and non-obvious logic. Single root element per component. Tailwind standard scale only (no arbitrary bracket values). Icons via `Components/Ui/Icon.vue` (aria-hidden). Focus-visible rings mirror hover.
- Pest browser tests assert rendered DOM elements, not text/values also present in the Inertia props JSON.
- Never `env()` outside config; `Model::query()` not `DB::`. After editing PHP run `vendor/bin/pint --dirty --format agent`; after editing JS/Vue run `npm run build`.
- Series columns are downsampled with a 240-point cap (matches `health:heart_rate --max-points=240`). Speed stored in m/s (raw); display converts. Time stored as absolute `Y-m-d H:i:s` (activity start + stream offset), matching `heart_rate`.
- Run PHP tests with `php artisan test --compact --filter=<name>`. Conventional commits, no attribution footer. Branch: `feat/activity-stream-charts` (off master).

---

### Task 1: Migration + Activity model columns

**Files:**
- Create: `database/migrations/<timestamp>_add_stream_columns_to_activities.php` (via `make:migration`)
- Modify: `app/Models/Activity.php`
- Test: `tests/Feature/ActivityStreamColumnsTest.php`

**Interfaces:**
- Produces: `Activity` with fillable + `array`-cast `altitude`, `speed`, `track` columns (alongside the existing `heart_rate`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ActivityStreamColumnsTest.php`:

```php
<?php

use App\Models\Activity;

it('stores and casts the stream columns as arrays', function () {
    $activity = Activity::factory()->create([
        'altitude' => [['time' => '2024-01-01 00:00:00', 'value' => 12.5]],
        'speed' => [['time' => '2024-01-01 00:00:00', 'value' => 3.2]],
        'track' => [['time' => '2024-01-01 00:00:00', 'lat' => 51.5, 'lng' => -0.1]],
    ]);

    $fresh = $activity->fresh();

    expect($fresh->altitude)->toBeArray()->and($fresh->altitude[0]['value'])->toBe(12.5);
    expect($fresh->speed[0]['value'])->toBe(3.2);
    expect($fresh->track[0]['lat'])->toBe(51.5);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ActivityStreamColumns`
Expected: FAIL (columns not fillable / not present).

- [ ] **Step 3: Generate and write the migration**

Run: `php artisan make:migration add_stream_columns_to_activities --no-interaction`

Replace the generated file's body with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->json('altitude')->nullable()->after('heart_rate');
            $table->json('speed')->nullable()->after('altitude');
            $table->json('track')->nullable()->after('speed');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropColumn(['altitude', 'speed', 'track']);
        });
    }
};
```

- [ ] **Step 4: Update the Activity model**

In `app/Models/Activity.php`, add `'altitude'`, `'speed'`, `'track'` to the `#[Fillable([...])]` list (after `'heart_rate'`), and add to `casts()`:

```php
            'altitude' => 'array',
            'speed' => 'array',
            'track' => 'array',
```

- [ ] **Step 5: Migrate and run the test**

Run: `php artisan migrate --no-interaction && php artisan test --compact --filter=ActivityStreamColumns`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/Activity.php tests/Feature/ActivityStreamColumnsTest.php
git commit -m "feat: add altitude/speed/track stream columns to activities"
```

---

### Task 2: Shared downsample-by-indices helper

**Files:**
- Create: `app/Support/Downsample.php`
- Modify: `app/Console/Commands/Fetch/ImportHealthHeartRate.php` (delegate its `downsample()` to the helper)
- Test: `tests/Feature/DownsampleTest.php`

**Interfaces:**
- Produces: `App\Support\Downsample::indices(int $total, int $cap): array<int,int>` — the evenly-spaced indices to keep (all of `0..total-1` when `cap` is 0 or `total <= cap`). Used to sample multiple parallel series by the SAME indices so they stay aligned.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DownsampleTest.php`:

```php
<?php

use App\Support\Downsample;

it('returns every index when under the cap', function () {
    expect(Downsample::indices(3, 240))->toBe([0, 1, 2]);
    expect(Downsample::indices(5, 0))->toBe([0, 1, 2, 3, 4]);
});

it('picks evenly spaced indices including both ends when over the cap', function () {
    $indices = Downsample::indices(100, 5);

    expect($indices)->toHaveCount(5);
    expect($indices[0])->toBe(0);
    expect($indices[4])->toBe(99);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=Downsample`
Expected: FAIL (class not found).

- [ ] **Step 3: Create the helper**

Create `app/Support/Downsample.php`:

```php
<?php

namespace App\Support;

/**
 * Evenly-spaced index selection for downsampling a series to a point cap.
 * Returning indices (rather than a reduced array) lets several parallel
 * series be sampled by the SAME indices so they stay aligned point-for-point.
 */
class Downsample
{
    /**
     * @return array<int, int>
     */
    public static function indices(int $total, int $cap): array
    {
        if ($cap === 0 || $total <= $cap) {
            return range(0, max($total - 1, 0));
        }

        $step = ($total - 1) / ($cap - 1);
        $indices = [];

        for ($index = 0; $index < $cap; $index++) {
            $indices[] = (int) round($index * $step);
        }

        return $indices;
    }
}
```

Note: when `$total` is 0, `range(0, -1)` yields `[0, -1]`; guard callers to skip empty series (the streams action in Task 3 only samples non-empty series), or add an explicit `$total === 0 ? [] : ...`. Add the empty guard here:

```php
        if ($total === 0) {
            return [];
        }
```

(placed as the first statement in `indices()`).

- [ ] **Step 4: Refactor ImportHealthHeartRate to delegate**

In `app/Console/Commands/Fetch/ImportHealthHeartRate.php`, replace the body of its private `downsample()` with a delegation (keep the method signature so its callers are unchanged):

```php
    /**
     * @param  array<int, mixed>  $series
     * @return array<int, mixed>
     */
    private function downsample(array $series, int $cap): array
    {
        return array_map(
            fn (int $index): mixed => $series[$index],
            \App\Support\Downsample::indices(count($series), $cap),
        );
    }
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter="Downsample|HealthHeartRate"`
Expected: PASS (new helper tests + existing heart-rate tests still green).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Downsample.php app/Console/Commands/Fetch/ImportHealthHeartRate.php tests/Feature/DownsampleTest.php
git commit -m "feat: add shared downsample-by-indices helper"
```

---

### Task 3: StoreActivityStreams action + StravaSync wiring

**Files:**
- Create: `app/Actions/StoreActivityStreams.php`
- Modify: `app/Console/Commands/Sync/StravaSync.php` (call the action for new activities)
- Test: `tests/Feature/StoreActivityStreamsTest.php`

**Interfaces:**
- Consumes: `App\Services\Strava::activityStreams(id, keys)`, `App\Support\Downsample`.
- Produces: `StoreActivityStreams::__invoke(App\Models\Activity $activity, App\Services\Strava $strava): bool` — fetches the streams for `$activity->source_id`, builds aligned downsampled absolute-time series, saves `altitude`/`speed`/`track` (and `heart_rate` when the `heartrate` stream is present), returns true when any series was stored.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/StoreActivityStreamsTest.php`:

```php
<?php

use App\Actions\StoreActivityStreams;
use App\Models\Activity;
use App\Services\Strava;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.strava.client_id' => 'x', 'services.strava.client_secret' => 'y', 'services.strava.refresh_token' => 'z']);
    Http::fake([
        '*oauth/token*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        '*/streams*' => Http::response([
            'time' => ['data' => [0, 1, 2]],
            'altitude' => ['data' => [10.0, 11.0, 12.0]],
            'velocity_smooth' => ['data' => [2.0, 2.5, 3.0]],
            'latlng' => ['data' => [[51.5, -0.1], [51.6, -0.2], [51.7, -0.3]]],
            'heartrate' => ['data' => [120, 130, 140]],
        ]),
    ]);
});

it('stores aligned absolute-time streams on the activity', function () {
    $activity = Activity::factory()->create(['source_id' => '999', 'occurred_at' => '2024-01-01 08:00:00']);

    $stored = app(StoreActivityStreams::class)($activity->fresh(), app(Strava::class));

    expect($stored)->toBeTrue();
    $activity->refresh();
    expect($activity->altitude)->toHaveCount(3);
    expect($activity->altitude[0])->toBe(['time' => '2024-01-01 08:00:00', 'value' => 10.0]);
    expect($activity->speed[2]['value'])->toBe(3.0);
    expect($activity->track[1])->toBe(['time' => '2024-01-01 08:00:01', 'lat' => 51.6, 'lng' => -0.2]);
    expect($activity->heart_rate[0])->toBe(['time' => '2024-01-01 08:00:00', 'bpm' => 120]);
});

it('returns false and stores nothing when Strava has no streams', function () {
    Http::fake([
        '*oauth/token*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        '*/streams*' => Http::response([]),
    ]);
    $activity = Activity::factory()->create(['source_id' => '998']);

    expect(app(StoreActivityStreams::class)($activity, app(Strava::class)))->toBeFalse();
    expect($activity->fresh()->altitude)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=StoreActivityStreams`
Expected: FAIL (class not found).

- [ ] **Step 3: Create the action**

Create `app/Actions/StoreActivityStreams.php`:

```php
<?php

namespace App\Actions;

use App\Models\Activity;
use App\Services\Strava;
use App\Support\Downsample;
use Carbon\CarbonImmutable;

class StoreActivityStreams
{
    private const KEYS = ['time', 'distance', 'latlng', 'altitude', 'velocity_smooth', 'heartrate'];

    private const CAP = 240;

    /**
     * Fetch, downsample, and store the activity's Strava streams. Returns true
     * when at least one series was stored.
     */
    public function __invoke(Activity $activity, Strava $strava): bool
    {
        $streams = $strava->activityStreams($activity->source_id, self::KEYS);

        $time = $streams['time']['data'] ?? null;
        if (! is_array($time) || $time === []) {
            return false;
        }

        $start = CarbonImmutable::parse($activity->occurred_at);
        $indices = Downsample::indices(count($time), self::CAP);
        $stamp = fn (int $i): string => $start->addSeconds((int) $time[$i])->format('Y-m-d H:i:s');

        $altitude = $streams['altitude']['data'] ?? null;
        $speed = $streams['velocity_smooth']['data'] ?? null;
        $latlng = $streams['latlng']['data'] ?? null;
        $heartrate = $streams['heartrate']['data'] ?? null;

        $attributes = [];

        if (is_array($altitude)) {
            $attributes['altitude'] = array_map(fn (int $i): array => ['time' => $stamp($i), 'value' => $altitude[$i]], $indices);
        }
        if (is_array($speed)) {
            $attributes['speed'] = array_map(fn (int $i): array => ['time' => $stamp($i), 'value' => $speed[$i]], $indices);
        }
        if (is_array($latlng)) {
            $attributes['track'] = array_map(fn (int $i): array => ['time' => $stamp($i), 'lat' => $latlng[$i][0], 'lng' => $latlng[$i][1]], $indices);
        }
        if (is_array($heartrate)) {
            $attributes['heart_rate'] = array_map(fn (int $i): array => ['time' => $stamp($i), 'bpm' => $heartrate[$i]], $indices);
        }

        if ($attributes === []) {
            return false;
        }

        $activity->update($attributes);

        return true;
    }
}
```

- [ ] **Step 4: Wire into StravaSync**

In `app/Console/Commands/Sync/StravaSync.php`, after a new activity is created (where `createActivity()` is called and photos are downloaded), invoke the action so freshly-synced activities get their streams. Inject/resolve `StoreActivityStreams` and call `app(StoreActivityStreams::class)($activity, $strava)` for each new activity (mirror how `GenerateStaticMap` is already called in the sync loop). Read the sync loop first to place the call correctly; a Strava instance is already available in that scope.

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter="StoreActivityStreams|StravaSync"`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/StoreActivityStreams.php app/Console/Commands/Sync/StravaSync.php tests/Feature/StoreActivityStreamsTest.php
git commit -m "feat: fetch and store strava activity streams"
```

---

### Task 4: strava:streams backfill command + HR precedence guard

**Files:**
- Create: `app/Console/Commands/Sync/StravaStreams.php`
- Modify: `app/Console/Commands/Fetch/ImportHealthHeartRate.php` (don't clobber Strava-sourced HR)
- Test: `tests/Feature/StravaStreamsCommandTest.php`

**Interfaces:**
- Signature: `strava:streams {--force : Refetch activities that already have streams}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/StravaStreamsCommandTest.php`:

```php
<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.strava.client_id' => 'x', 'services.strava.client_secret' => 'y', 'services.strava.refresh_token' => 'z']);
    Http::fake([
        '*oauth/token*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        '*/streams*' => Http::response([
            'time' => ['data' => [0, 1]],
            'altitude' => ['data' => [10.0, 11.0]],
            'latlng' => ['data' => [[51.5, -0.1], [51.6, -0.2]]],
        ]),
    ]);
});

it('backfills streams for route-bearing activities and skips ones already done', function () {
    $withRoute = Activity::factory()->create(['source' => 'strava', 'source_id' => '1', 'meta' => ['polyline' => '_p~iF']]);
    $alreadyDone = Activity::factory()->create(['source' => 'strava', 'source_id' => '2', 'meta' => ['polyline' => 'abc'], 'altitude' => [['time' => 't', 'value' => 1]]]);

    $this->artisan('strava:streams')->assertSuccessful();

    expect($withRoute->fresh()->altitude)->not->toBeNull();
    // untouched (already had altitude, not forced)
    expect($alreadyDone->fresh()->altitude)->toHaveCount(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=StravaStreamsCommand`
Expected: FAIL (command not defined).

- [ ] **Step 3: Create the command**

Create `app/Console/Commands/Sync/StravaStreams.php`:

```php
<?php

namespace App\Console\Commands\Sync;

use App\Actions\StoreActivityStreams;
use App\Models\Activity;
use App\Services\Strava;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('strava:streams {--force : Refetch activities that already have streams}')]
#[Description('Backfill Strava altitude/speed/track/heart-rate streams onto route-bearing activities')]
class StravaStreams extends Command
{
    public function handle(StoreActivityStreams $store, Strava $strava): int
    {
        $query = Activity::query()
            ->where('source', 'strava')
            ->whereNotNull('meta->polyline');

        if (! $this->option('force')) {
            $query->whereNull('altitude');
        }

        $activities = $query->get();
        $done = 0;

        foreach ($activities as $activity) {
            if ($store($activity, $strava)) {
                $done++;
                $this->components->task("{$activity->name}");
            }
        }

        $this->components->info("Stored streams for {$done} of {$activities->count()} activities.");

        return self::SUCCESS;
    }
}
```

Note on rate limiting: Strava allows ~100 requests / 15 min and ~1000 / day. Running against all ~741 activities exceeds the 15-minute window, so the operator runs the command across multiple sessions; the `whereNull('altitude')` skip makes it resumable (already-done activities are not refetched). Do NOT add a blocking `sleep()` loop; document the multi-run approach in the command's post-run output instead (the `--force` flag is the only way to refetch).

- [ ] **Step 4: Guard ImportHealthHeartRate against clobbering Strava HR**

In `app/Console/Commands/Fetch/ImportHealthHeartRate.php`, the Apple-Health import must not overwrite a Strava-sourced HR series. A Strava-streamed activity is identifiable by having an `altitude` series. In the activity selection/apply path, skip activities where `altitude` is non-null (unless the existing `--overwrite` option is set). Read the command's `apply()`/selection logic first; add the guard at the point where it decides to write `heart_rate` for an activity:

```php
        if ($activity->altitude !== null && ! $this->option('overwrite')) {
            return /* the "unchanged" result this method returns */;
        }
```

Match the method's existing return shape. Add a test asserting an activity with an `altitude` series keeps its Strava `heart_rate` after the health import runs.

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter="StravaStreamsCommand|HealthHeartRate"`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/Sync/StravaStreams.php app/Console/Commands/Fetch/ImportHealthHeartRate.php tests/Feature/StravaStreamsCommandTest.php
git commit -m "feat: add strava:streams backfill; keep strava heart-rate over apple health"
```

---

### Task 5: EntryController deferred profile prop

**Files:**
- Modify: `app/Http/Controllers/EntryController.php`
- Test: `tests/Feature/EntryProfilePropTest.php`

**Interfaces:**
- Produces: the `Entry` render gets a deferred `profile` prop for activity entries — `{ heart_rate, altitude, speed, track }` — and the raw series are excluded from the main `entry` payload.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/EntryProfilePropTest.php`:

```php
<?php

use App\Models\Activity;

it('exposes a deferred profile prop for an activity with streams', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => now(),
        'altitude' => [['time' => '2024-01-01 00:00:00', 'value' => 10]],
        'track' => [['time' => '2024-01-01 00:00:00', 'lat' => 51.5, 'lng' => -0.1]],
    ]);

    // Deferred props resolve on a partial reload requesting "profile".
    $this->get($activity->url(), ['X-Inertia' => true, 'X-Inertia-Partial-Data' => 'profile', 'X-Inertia-Partial-Component' => 'Entry', 'X-Inertia-Version' => ''])
        ->assertJsonPath('props.profile.altitude.0.value', 10);
});
```

Note: confirm the activity single-entry URL (`$activity->url()`) and the exact partial-reload header set this app/Inertia version expects (see how `TimelineController`'s deferred `groups` is tested, if a test exists); adjust headers to match. The assertion must read the resolved deferred prop.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EntryProfileProp`
Expected: FAIL (no `profile` prop).

- [ ] **Step 3: Add the deferred prop and exclude raw series**

In `app/Http/Controllers/EntryController.php`:

- Add a `profile` prop to the `Inertia::render('Entry', [...])` array, deferred, only meaningful for activities:

```php
            'profile' => $model instanceof Activity
                ? Inertia::defer(fn (): array => [
                    'heart_rate' => $model->heart_rate,
                    'altitude' => $model->altitude,
                    'speed' => $model->speed,
                    'track' => $model->track,
                ])
                : null,
```

- In `entryPayload()` (the method building the `entry` array via `Arr::except($model->toArray(), ...)`), add `'heart_rate', 'altitude', 'speed', 'track'` to the excluded keys so the large series aren't double-sent in the main payload.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter="EntryProfileProp|Entry"`
Expected: PASS.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/EntryController.php tests/Feature/EntryProfilePropTest.php
git commit -m "feat: expose deferred activity profile prop"
```

---

### Task 6: Shared cursor + ActivityProfile charts

**Files:**
- Create: `resources/js/composables/useActivityCursor.js`
- Create: `resources/js/lib/crosshairPlugin.js`
- Modify: `resources/js/Components/Ui/Chart.vue` (accept `plugins`, emit `hover`)
- Create: `resources/js/Components/Entry/ActivityProfile.vue`
- Test: `tests/Browser/ActivityProfileTest.php`

**Interfaces:**
- Produces: `useActivityCursor()` returning `{ index (ref, null when inactive), set(i), clear() }`.
- Produces: `ActivityProfile.vue` props `{ profile: Object, cursor: Object }` (cursor from the composable) rendering up to three charts.

- [ ] **Step 1: Write the failing browser test**

Create `tests/Browser/ActivityProfileTest.php`:

```php
<?php

use App\Models\Activity;

it('renders profile chart canvases on an activity with streams', function () {
    $activity = Activity::factory()->create([
        'type' => 'run',
        'occurred_at' => now(),
        'meta' => ['polyline' => 'ki~mHvfyL...'],
        'altitude' => collect(range(0, 20))->map(fn ($i) => ['time' => now()->addSeconds($i)->format('Y-m-d H:i:s'), 'value' => 10 + $i])->all(),
        'speed' => collect(range(0, 20))->map(fn ($i) => ['time' => now()->addSeconds($i)->format('Y-m-d H:i:s'), 'value' => 2 + $i / 10])->all(),
    ]);

    $page = visit($activity->url());

    // Rendered DOM: a chart canvas for the profile section.
    $page->assertPresent('[data-testid="activity-profile"] canvas');
});
```

Adjust the polyline to a valid short encoded string and confirm `$activity->url()` reaches the activity page. The test asserts a rendered `<canvas>`, not props JSON.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Browser/ActivityProfileTest.php`
Expected: FAIL (no profile section).

- [ ] **Step 3: Create the cursor composable**

Create `resources/js/composables/useActivityCursor.js`:

```js
import { ref } from 'vue';

// A single shared cursor index across the profile charts and the route map.
// null means "not hovering" (hide the crosshair line and the map dot).
export function useActivityCursor() {
    const index = ref(null);

    // Set the active point index (clamped by callers to their series length).
    function set(value) {
        index.value = value;
    }

    // Clear on pointer leave so the line and dot disappear together.
    function clear() {
        index.value = null;
    }

    return { index, set, clear };
}
```

- [ ] **Step 4: Create the crosshair plugin**

Create `resources/js/lib/crosshairPlugin.js`:

```js
// A Chart.js inline plugin that draws a vertical line at a shared cursor index.
// The index is read from the chart's `options.plugins.crosshair.index` so all
// charts sharing one reactive cursor draw the line at the same x.
export const crosshairPlugin = {
    id: 'crosshair',
    afterDatasetsDraw(chart) {
        const cfg = chart.options.plugins?.crosshair;
        const index = cfg?.index;

        if (index == null || index < 0) {
            return;
        }

        const meta = chart.getDatasetMeta(0);
        const point = meta?.data?.[index];

        if (!point) {
            return;
        }

        const { ctx, chartArea } = chart;
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(point.x, chartArea.top);
        ctx.lineTo(point.x, chartArea.bottom);
        ctx.lineWidth = 1;
        ctx.strokeStyle = cfg.color || 'rgba(120,120,120,0.6)';
        ctx.stroke();
        ctx.restore();
    },
};
```

- [ ] **Step 5: Extend Chart.vue to accept plugins and emit hover**

In `resources/js/Components/Ui/Chart.vue`:

- Add a prop `plugins: { type: Array, default: () => [] }`.
- Pass it into the Chart config: `new Chart(canvas.value, { type: props.type, data: props.data, options: props.options, plugins: props.plugins })`.
- Add `const emit = defineEmits(['hover']);` and set an `onHover` in the merged options that emits the nearest index:

```js
// Emit the data index under the pointer so a parent can drive a shared cursor.
function handleHover(event, _elements, chart) {
    const points = chart.getElementsAtEventForMode(event, 'index', { intersect: false }, false);
    emit('hover', points.length ? points[0].index : null);
}
```

Merge `onHover: handleHover` into the options passed to Chart.js (without mutating `props.options`). Because `rebuildChart` reads `props.options`, compose the final options as `{ ...props.options, onHover: handleHover }` at construction.

- [ ] **Step 6: Create ActivityProfile.vue**

Create `resources/js/Components/Entry/ActivityProfile.vue`:

```vue
<script setup>
import { computed } from 'vue';
import Chart from '../Ui/Chart.vue';
import { crosshairPlugin } from '../../lib/crosshairPlugin.js';

const props = defineProps({
    // { heart_rate, altitude, speed, track } arrays of {time, value|bpm|lat/lng}.
    profile: { type: Object, required: true },
    // Shared cursor from useActivityCursor (index ref + set/clear).
    cursor: { type: Object, required: true },
});

// Each metric that actually has data, in display order, with its colour and value key.
const metrics = computed(() =>
    [
        { key: 'heart_rate', label: 'Heart rate', unit: 'bpm', valueKey: 'bpm', color: 'var(--color-run)' },
        { key: 'altitude', label: 'Elevation', unit: 'm', valueKey: 'value', color: 'var(--color-walk)' },
        { key: 'speed', label: 'Speed', unit: 'm/s', valueKey: 'value', color: 'var(--color-ride)' },
    ].filter((metric) => Array.isArray(props.profile[metric.key]) && props.profile[metric.key].length > 0),
);

// Chart.js data for one metric: elapsed-time labels (shared index) + its values.
function chartData(metric) {
    const series = props.profile[metric.key];
    return {
        labels: series.map((_, index) => index),
        datasets: [{ data: series.map((point) => point[metric.valueKey]), borderColor: metric.color, fill: metric.key === 'altitude', tension: 0.3, pointRadius: 0 }],
    };
}

// Options carry the shared cursor index so the crosshair plugin draws in sync.
function chartOptions(metric) {
    return {
        animation: false,
        scales: { x: { display: false }, y: { title: { display: true, text: metric.unit } } },
        plugins: { legend: { display: false }, crosshair: { index: props.cursor.index.value, color: metric.color } },
    };
}
</script>

<template>
    <div data-testid="activity-profile" class="space-y-4">
        <div v-for="metric in metrics" :key="metric.key">
            <p class="text-label uppercase text-neutral-500">{{ metric.label }}</p>
            <Chart
                type="line"
                :data="chartData(metric)"
                :options="chartOptions(metric)"
                :plugins="[crosshairPlugin]"
                :height="140"
                @hover="cursor.set($event)"
                @pointerleave="cursor.clear()"
            />
        </div>
    </div>
</template>
```

Note: `@pointerleave` must reach the chart canvas — if `Chart.vue`'s root doesn't forward it, add a wrapping element with `@pointerleave` in ActivityProfile (the wrapper `div` around each `<Chart>` can carry it). Verify against `Chart.vue`'s root element.

- [ ] **Step 7: Build and run the test**

Run: `npm run build && php artisan test --compact tests/Browser/ActivityProfileTest.php`
Expected: PASS (canvas rendered). If the cursor reactivity needs `chartOptions` to re-run on `cursor.index` change, confirm `Chart.vue`'s `watch(() => [props.data, props.options], ...)` picks up the changed options object (ActivityProfile passes a fresh options object as `cursor.index.value` changes).

**PERFORMANCE (important):** driving the crosshair by passing `cursor.index.value` through `options` (Step 6) makes `Chart.vue`'s `watch(props.options)` rebuild the whole chart on every pointer move — three chart rebuilds per mousemove, which is janky on mobile. Drive the crosshair WITHOUT a rebuild instead: have the `crosshair` plugin read the index from a shared mutable object (e.g. a module-level ref or a value stored on the chart instance) and, when `cursor.index` changes, call the chart's `render()` (a cheap redraw) rather than reconstructing it. Expose the chart instance from `Chart.vue` (e.g. `defineExpose({ redraw })`) or register the plugin so it reads the reactive cursor directly. Verify pointer moves redraw only (no `new Chart()` per move) before committing.

- [ ] **Step 8: Commit**

```bash
git add resources/js/composables/useActivityCursor.js resources/js/lib/crosshairPlugin.js resources/js/Components/Ui/Chart.vue resources/js/Components/Entry/ActivityProfile.vue tests/Browser/ActivityProfileTest.php
git commit -m "feat: activity profile charts with shared cursor"
```

---

### Task 7: EntryMap route dot + bidirectional scrub, wired in ActivityDetail

**Files:**
- Modify: `resources/js/Components/Maps/EntryMap.vue` (dot + pointer scrub)
- Modify: `resources/js/Components/Entry/ActivityDetail.vue` (mount ActivityProfile + share cursor with the map)
- Test: `tests/Browser/ActivityScrubTest.php`

**Interfaces:**
- `EntryMap` gains props `track: { type: Array, default: () => [] }` and `cursor: { type: Object, default: null }` (the composable). It renders a dot at `track[cursor.index]` and, on pointer move over the map, sets `cursor` to the nearest track point's index.

- [ ] **Step 1: Write the failing browser test**

Create `tests/Browser/ActivityScrubTest.php`:

```php
<?php

use App\Models\Activity;

it('renders the route map dot element for an activity with a track', function () {
    $activity = Activity::factory()->create([
        'type' => 'run',
        'occurred_at' => now(),
        'meta' => ['polyline' => 'ki~mHvfyL...'],
        'track' => [['time' => now()->format('Y-m-d H:i:s'), 'lat' => 51.5, 'lng' => -0.1]],
    ]);

    $page = visit($activity->url());

    // Rendered DOM: the scrub dot element exists in the map container.
    $page->assertPresent('[data-testid="route-dot"]');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Browser/ActivityScrubTest.php`
Expected: FAIL.

- [ ] **Step 3: Add the dot + scrub to EntryMap.vue**

In `resources/js/Components/Maps/EntryMap.vue`:

- Add props `track` (array of `{time, lat, lng}`) and `cursor` (the shared composable or null).
- After the map/route are ready, add a MapLibre `Marker` (an element with `data-testid="route-dot"`, a small coloured circle styled with standard Tailwind classes) that is shown only when `cursor?.index` is non-null, positioned at `[track[index].lng, track[index].lat]`. Watch `cursor.index` to move/hide it.
- Add a `map.on('mousemove', ...)` and `map.on('touchmove', ...)` (or a single pointer handler on the canvas) that projects the pointer to lng/lat, finds the nearest `track` point (linear scan over the ~240 downsampled points is fine), and calls `cursor.set(nearestIndex)`; clear on `mouseout`/`touchend`.
- Comment each new function per the Vue-comment convention.

Read the existing `onMounted` map setup and the photo-marker pattern first; reuse the same MapLibre marker approach for the dot.

- [ ] **Step 4: Wire ActivityProfile + shared cursor into ActivityDetail.vue**

In `resources/js/Components/Entry/ActivityDetail.vue`:

- Import `useActivityCursor`, `ActivityProfile`, and (if not already) `EntryMap`; import `Deferred` from `@inertiajs/vue3`.
- Create one `const cursor = useActivityCursor();` and pass it to BOTH the `EntryMap` (with `:track` from the profile) and `ActivityProfile` so they share the cursor.
- Wrap the profile in `<Deferred data="profile">` with a pulsing skeleton fallback (per the deferred-prop convention); render `EntryMap` with the route polyline (as ActivityDetail already does) plus `:track` and `:cursor`, and `ActivityProfile` with `:profile` and `:cursor` once loaded.

Read ActivityDetail.vue first to see how it currently renders the map and where the profile section should sit (below the map).

- [ ] **Step 5: Build and run the tests**

Run: `npm run build && php artisan test --compact tests/Browser/ActivityScrubTest.php tests/Browser/ActivityProfileTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/Maps/EntryMap.vue resources/js/Components/Entry/ActivityDetail.vue tests/Browser/ActivityScrubTest.php
git commit -m "feat: route scrub dot synced with activity profile cursor"
```

---

### Task 8: Backfill + verification (manual, not a test)

- [ ] **Step 1: Backfill streams (paced, resumable)**

```bash
php artisan strava:streams
# Re-run across sessions until it reports 0 remaining (Strava ~100 req/15min, ~1000/day).
```

- [ ] **Step 2: Verify end to end**

Open an activity with GPS data. Confirm the three charts render, hovering any chart moves the shared vertical line across all three AND the dot along the route, and scrubbing the route moves the cursor. Check touch on a mobile viewport. Confirm heart rate on a stream-fetched activity comes from Strava and Apple Health didn't overwrite it.

- [ ] **Step 3: Confirm no stray artifacts**

`git status` clean (stream data lives in the DB / CSV, not committed binaries).
