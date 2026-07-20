# Health Ingest Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn `POST /api/health/ingest` into one metric-routed endpoint that queues processing, discards raw payloads (logging a summary), and drives the existing sleep + heart-rate processors from a shared job.

**Architecture:** The controller authenticates, logs a compact summary, dispatches a `ProcessHealthExport` queued job with the decoded payload, and returns 200. The job routes each metric by name to a `HealthProcessor` (`SleepProcessor`, `HeartRateProcessor`) extracted from the two existing commands. Processors upsert typed rows and mirror to CSV (dev-only). The commands become thin wrappers.

**Tech Stack:** Laravel 13, PHP 8.4, Pest 4, database queue (`sync` in tests), `laravel/prompts`.

## Global Constraints

- PHP 8.4: constructor property promotion; explicit return types on every method; curly braces on all control structures.
- Prefer PHPDoc array shapes over inline comments; comments only where logic is non-obvious.
- Run `vendor/bin/pint --dirty --format agent` before every commit.
- Run tests with `php artisan test --compact` plus a `--filter`.
- Queue connection is `database`; tests run `QUEUE_CONNECTION=sync` (jobs run inline). No new dependencies.
- CSV mirroring stays for now (removed later in #84). The DB is the source of truth.
- No attribution lines in commit messages.

---

### Task 1: `HealthProcessor` contract + `SleepProcessor`

Extract the sleep ingest path out of `ImportHealthSleep` into a reusable processor that takes a decoded payload instead of reading disk.

**Files:**
- Create: `app/Support/Health/HealthProcessor.php`
- Create: `app/Support/Health/SleepProcessor.php`
- Modify: `app/Console/Commands/Fetch/ImportHealthSleep.php` (source of the methods to move)
- Test: `tests/Feature/Health/SleepProcessorTest.php`

**Interfaces:**
- Produces:
  - `interface HealthProcessor { /** @return list<string> */ public function handles(): array; /** @param array<string,mixed> $payload */ public function process(array $payload): void; }`
  - `SleepProcessor implements HealthProcessor` with constructor `__construct(private SleepAggregator $aggregator, private SleepScore $scorer)`, `handles(): ['sleep_analysis']`, `process(array $payload): void`, and public `scoreAll(): int` (used later by the command's `--score` mode).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Sleep;
use App\Support\Health\SleepProcessor;

it('upserts a night of sleep from a decoded payload', function () {
    $payload = [
        'data' => ['metrics' => [[
            'name' => 'sleep_analysis',
            'data' => [
                ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 02:30:00 +0000'],
                ['value' => 'Deep', 'source' => 'Oura', 'start' => '2026-01-11 02:30:00 +0000', 'end' => '2026-01-11 04:00:00 +0000'],
                ['value' => 'REM',  'source' => 'Oura', 'start' => '2026-01-11 04:00:00 +0000', 'end' => '2026-01-11 07:00:00 +0000'],
            ],
        ]]],
    ];

    app(SleepProcessor::class)->process($payload);

    expect(Sleep::query()->count())->toBe(1);
    $night = Sleep::query()->first();
    expect($night->source)->toBe('oura');
    expect($night->duration)->toBeGreaterThan(0);
});

it('is idempotent across repeated payloads', function () {
    $payload = [
        'data' => ['metrics' => [[
            'name' => 'sleep_analysis',
            'data' => [
                ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 03:00:00 +0000'],
                ['value' => 'REM',  'source' => 'Oura', 'start' => '2026-01-11 03:00:00 +0000', 'end' => '2026-01-11 07:00:00 +0000'],
            ],
        ]]],
    ];

    app(SleepProcessor::class)->process($payload);
    app(SleepProcessor::class)->process($payload);

    expect(Sleep::query()->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SleepProcessorTest`
Expected: FAIL (`Class "App\Support\Health\SleepProcessor" not found`).

- [ ] **Step 3: Create the interface**

`app/Support/Health/HealthProcessor.php`:

```php
<?php

namespace App\Support\Health;

interface HealthProcessor
{
    /**
     * Metric names (HAE `metric.name`) this processor consumes.
     *
     * @return list<string>
     */
    public function handles(): array;

    /**
     * Process a decoded Health Auto Export payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void;
}
```

- [ ] **Step 4: Create `SleepProcessor` by moving the ingest path from the command**

Create `app/Support/Health/SleepProcessor.php`. Move these methods verbatim out of `ImportHealthSleep` and into the processor, then wire them together:

- Move `mirrorToCsv`, `csvRow`, `csvPath`, `score` (rename to public `scoreAll(): int`), `bedtimeMinutes`, `wakeEvents`, `median`, `sourceRank`, `changedMeaningfully`, and the `COLUMNS`, `SCORE_COLUMNS`, `BASELINE_WINDOW`, `BASELINE_MINIMUM` constants.
- Replace the command's disk-reading `collectSegments()`/`payloads()` with a new `segmentsFrom(array $payload): array` (below).
- The public `process()` runs the command's old default `handle()` body: aggregate, upsert, mirror, score.

```php
<?php

namespace App\Support\Health;

use App\Models\Sleep;
use Illuminate\Support\Carbon;

class SleepProcessor implements HealthProcessor
{
    /** @var list<string> */
    private const COLUMNS = ['occurred_at', 'bedtime', 'wake_time', 'duration', 'awake', 'rem', 'core', 'deep', 'source', 'stages'];

    /** @var list<string> */
    private const SCORE_COLUMNS = ['score', 'duration_score', 'bedtime_score', 'interruption_score'];

    private const BASELINE_WINDOW = 14;

    private const BASELINE_MINIMUM = 3;

    public function __construct(private SleepAggregator $aggregator, private SleepScore $scorer) {}

    /**
     * @return list<string>
     */
    public function handles(): array
    {
        return ['sleep_analysis'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void
    {
        $segments = $this->segmentsFrom($payload);

        if ($segments === []) {
            return;
        }

        $records = $this->aggregator->aggregate($segments);

        foreach ($records as $record) {
            Sleep::query()->updateOrCreate(
                ['occurred_at' => Carbon::parse($record['occurred_at'])->startOfDay()],
                $record,
            );
        }

        $this->mirrorToCsv($records);
        $this->scoreAll();
    }

    /**
     * Extract and de-duplicate `sleep_analysis` segments from one payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function segmentsFrom(array $payload): array
    {
        $segments = [];
        $seen = [];

        foreach ($payload['data']['metrics'] ?? [] as $metric) {
            if (($metric['name'] ?? null) !== 'sleep_analysis' || ! is_array($metric['data'] ?? null)) {
                continue;
            }

            foreach ($metric['data'] as $segment) {
                $key = ($segment['source'] ?? '').'|'.($segment['start'] ?? '').'|'.($segment['end'] ?? '').'|'.($segment['value'] ?? '');

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    // ... moved verbatim from ImportHealthSleep: mirrorToCsv(), csvRow(),
    // csvPath(), scoreAll() (was score($scorer) -> use $this->scorer),
    // bedtimeMinutes(), wakeEvents(), median(), sourceRank(),
    // changedMeaningfully(). Change score()'s `SleepScore $scorer` parameter
    // to use the injected `$this->scorer`, and make it `public function
    // scoreAll(): int`.
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=SleepProcessorTest`
Expected: PASS (2 passed).

- [ ] **Step 6: Slim `ImportHealthSleep` in the same task (keep the suite green)**

Because the methods above moved out of the command, the command must be slimmed now, in this task, or it will reference missing methods. Rewrite `ImportHealthSleep` to delegate ingest to `SleepProcessor` and keep only the maintenance modes.

New signature:

```php
#[Signature('health:sleep {--file= : Process a specific raw JSON payload path} {--resplit : Re-derive existing rows from stored stages} {--redate : Re-date every row to the Apple sleep-day and resolve collisions} {--score : Recompute the sleep score for every row}')]
```

New `handle()`:

```php
public function handle(SleepProcessor $processor, SleepAggregator $aggregator): int
{
    if ($this->option('resplit')) {
        return $this->resplit($aggregator);
    }

    if ($this->option('redate')) {
        return $this->redate($aggregator);
    }

    if ($this->option('score')) {
        $processor->scoreAll();

        return self::SUCCESS;
    }

    $file = $this->option('file');

    if (! is_string($file) || $file === '') {
        $this->components->info('Sleep now ingests from POST /api/health/ingest. Use --file to reprocess a payload, or --resplit/--redate/--score for maintenance.');

        return self::SUCCESS;
    }

    $payload = json_decode((string) file_get_contents($file), true);

    if (! is_array($payload)) {
        $this->components->error("Not a JSON payload: {$file}");

        return self::FAILURE;
    }

    $processor->process($payload);
    $this->components->info('Processed sleep payload.');

    return self::SUCCESS;
}
```

Remove from the command every method now living in `SleepProcessor` (`mirrorToCsv`, `csvRow`, `csvPath`, `score`, `bedtimeMinutes`, `wakeEvents`, `median`, `collectSegments`, `payloads`). Keep `resplit`, `redate`, `sourceRank`, `changedMeaningfully` for the maintenance modes; where `resplit`/`redate` need CSV mirroring, call the injected `$processor`'s public helpers rather than re-adding a private copy. Do NOT leave duplicated method bodies in the command.

- [ ] **Step 7: Run the full suite to confirm nothing is broken mid-refactor**

Run: `php artisan test --compact --filter=Health`
Expected: PASS (SleepProcessor tests plus any existing health:sleep test still green).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Health/HealthProcessor.php app/Support/Health/SleepProcessor.php app/Console/Commands/Fetch/ImportHealthSleep.php tests/Feature/Health/SleepProcessorTest.php
git commit -m "refactor: extract SleepProcessor, slim health:sleep to a wrapper"
```

---

### Task 2: `HeartRateProcessor`

Extract the heart-rate ingest path out of `ImportHealthHeartRate`.

**Files:**
- Create: `app/Support/Health/HeartRateProcessor.php`
- Modify: `app/Console/Commands/Fetch/ImportHealthHeartRate.php` (source of methods to move)
- Test: `tests/Feature/Health/HeartRateProcessorTest.php`

**Interfaces:**
- Consumes: `HealthProcessor` (Task 1), existing `App\Support\Health\HeartRateMatcher`, `App\Support\Downsample`.
- Produces: `HeartRateProcessor implements HealthProcessor` with constructor `__construct(private HeartRateMatcher $matcher)`, `handles(): ['heart_rate']`, `process(array $payload): void`. Defaults: cap 240, no overwrite.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Activity;
use App\Support\Health\HeartRateProcessor;

it('fills missing heart-rate on an activity from window-matched samples', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-01-10 09:00:00',
        'duration' => 1800,
        'altitude' => null,
        'average_heart_rate' => null,
        'max_heart_rate' => null,
        'heart_rate' => null,
    ]);

    $payload = ['data' => ['metrics' => [[
        'name' => 'heart_rate',
        'data' => [
            ['start' => '2026-01-10 09:05:00 +0000', 'Avg' => 130, 'Max' => 150, 'source' => 'Apple Watch'],
            ['start' => '2026-01-10 09:15:00 +0000', 'Avg' => 140, 'Max' => 165, 'source' => 'Apple Watch'],
        ],
    ]]]];

    app(HeartRateProcessor::class)->process($payload);

    $activity->refresh();
    expect($activity->average_heart_rate)->not->toBeNull();
    expect($activity->max_heart_rate)->toBeGreaterThanOrEqual(150);
});

it('does not clobber an activity that already has a Strava stream', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-01-10 09:00:00',
        'duration' => 1800,
        'altitude' => [1, 2, 3],
        'average_heart_rate' => 120,
        'max_heart_rate' => 145,
    ]);

    $payload = ['data' => ['metrics' => [[
        'name' => 'heart_rate',
        'data' => [['start' => '2026-01-10 09:05:00 +0000', 'Avg' => 200, 'Max' => 210, 'source' => 'Apple Watch']],
    ]]]];

    app(HeartRateProcessor::class)->process($payload);

    $activity->refresh();
    expect($activity->average_heart_rate)->toBe(120);
    expect($activity->max_heart_rate)->toBe(145);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=HeartRateProcessorTest`
Expected: FAIL (`Class "App\Support\Health\HeartRateProcessor" not found`).

- [ ] **Step 3: Create `HeartRateProcessor` by moving the ingest path**

Create `app/Support/Health/HeartRateProcessor.php`. Move `apply`, `storedSeries`, `downsample`, and `mirrorToCsv`/`csvPath` verbatim from `ImportHealthHeartRate`. Replace disk-reading `samplesFrom($path)`/`loadPayload`/`payloadPaths` with `samplesFrom(array $payload)` below. `apply()` currently reads `$this->option('overwrite')`/`$this->option('dry')`; change it to fixed ingest behaviour: never overwrite, always write (no dry).

```php
<?php

namespace App\Support\Health;

use App\Models\Activity;
use App\Support\Downsample;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class HeartRateProcessor implements HealthProcessor
{
    private const MAX_POINTS = 240;

    public function __construct(private HeartRateMatcher $matcher) {}

    /**
     * @return list<string>
     */
    public function handles(): array
    {
        return ['heart_rate'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void
    {
        $samples = $this->samplesFrom($payload);

        if ($samples === []) {
            return;
        }

        /** @var Collection<int, Activity> $activities */
        $activities = Activity::query()->get()->keyBy('id');
        $changed = [];

        foreach ($this->matcher->match($samples, $activities) as $id => $aggregate) {
            $activity = $activities->get($id);

            if ($activity instanceof Activity) {
                $changed[$activity->occurred_at->format('Y-m-d H:i:s')] = $this->apply($activity, $aggregate, self::MAX_POINTS);
            }
        }

        if ($changed !== []) {
            $this->mirrorToCsv($changed);
        }
    }

    /**
     * Extract and de-duplicate `heart_rate` samples from one payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array{time: int, avg: int, max: int, source: string}>
     */
    private function samplesFrom(array $payload): array
    {
        $samples = [];

        foreach ($payload['data']['metrics'] ?? [] as $metric) {
            if (($metric['name'] ?? null) !== 'heart_rate' || ! is_array($metric['data'] ?? null)) {
                continue;
            }

            foreach ($metric['data'] as $point) {
                $stamp = $point['start'] ?? $point['date'] ?? null;
                $average = $point['Avg'] ?? $point['avg'] ?? null;

                if (! is_string($stamp) || ! is_numeric($average)) {
                    continue;
                }

                $source = trim((string) ($point['source'] ?? 'unknown')) ?: 'unknown';
                $time = Carbon::parse($stamp)->getTimestamp();

                $samples[$source.'|'.$time] = [
                    'time' => $time,
                    'avg' => (int) round((float) $average),
                    'max' => (int) round((float) ($point['Max'] ?? $average)),
                    'source' => $source,
                ];
            }
        }

        return array_values($samples);
    }

    // ... moved from ImportHealthHeartRate: apply() (drop the --overwrite and
    // --dry option reads: treat overwrite as false and always save),
    // storedSeries(), downsample(), mirrorToCsv(), csvPath().
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=HeartRateProcessorTest`
Expected: PASS (2 passed).

- [ ] **Step 5: Slim `ImportHealthHeartRate` in the same task (keep the suite green)**

The moved methods must leave the command now. Rewrite it to delegate to `HeartRateProcessor`:

```php
#[Signature('health:heart_rate {--file= : Process a specific raw JSON payload path}')]
```

```php
public function handle(HeartRateProcessor $processor): int
{
    $file = $this->option('file');

    if (! is_string($file) || $file === '') {
        $this->components->info('Heart-rate now ingests from POST /api/health/ingest. Use --file to reprocess a payload.');

        return self::SUCCESS;
    }

    $payload = json_decode((string) file_get_contents($file), true);

    if (! is_array($payload)) {
        $this->components->error("Not a JSON payload: {$file}");

        return self::FAILURE;
    }

    $processor->process($payload);
    $this->components->info('Processed heart-rate payload.');

    return self::SUCCESS;
}
```

Remove the moved methods from the command (`apply`, `storedSeries`, `downsample`, `samplesFrom`, `loadPayload`, `payloadPaths`, `mirrorToCsv`, `csvPath`). Drop the now-unused imports (`Storage`, `Downsample`, `Carbon`, etc.) that only the removed methods used.

- [ ] **Step 6: Run the full health suite**

Run: `php artisan test --compact --filter=Health`
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Health/HeartRateProcessor.php app/Console/Commands/Fetch/ImportHealthHeartRate.php tests/Feature/Health/HeartRateProcessorTest.php
git commit -m "refactor: extract HeartRateProcessor, slim health:heart_rate to a wrapper"
```

---

### Task 3: `HealthPayloadSummary` helper

Relocate the controller's `summarise()` into a reusable helper for the ingest log line.

**Files:**
- Create: `app/Support/Health/HealthPayloadSummary.php`
- Test: `tests/Unit/HealthPayloadSummaryTest.php`

**Interfaces:**
- Produces: `HealthPayloadSummary::for(array $payload): array` (static) returning `['top_level_keys', 'data_keys', 'metric_count', 'metrics', 'workout_count', 'workouts']`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Support\Health\HealthPayloadSummary;

it('summarises metrics and workouts compactly', function () {
    $summary = HealthPayloadSummary::for(['data' => [
        'metrics' => [['name' => 'sleep_analysis', 'units' => 'hr', 'data' => [['value' => 'Core']]]],
        'workouts' => [['name' => 'Run', 'start' => 'a', 'end' => 'b']],
    ]]);

    expect($summary['metric_count'])->toBe(1);
    expect($summary['metrics'][0]['name'])->toBe('sleep_analysis');
    expect($summary['metrics'][0]['points'])->toBe(1);
    expect($summary['workout_count'])->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=HealthPayloadSummaryTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Create the helper**

Move the body of `HealthExportController::summarise()` into a static `for()`:

```php
<?php

namespace App\Support\Health;

class HealthPayloadSummary
{
    /**
     * Compact structural summary of a Health Auto Export payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function for(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $metrics = array_map(fn (array $metric): array => [
            'name' => $metric['name'] ?? null,
            'units' => $metric['units'] ?? null,
            'points' => is_array($metric['data'] ?? null) ? count($metric['data']) : 0,
            'fields' => is_array($metric['data'][0] ?? null) ? array_keys($metric['data'][0]) : [],
            'sample' => $metric['data'][0] ?? null,
        ], array_values(array_filter($data['metrics'] ?? [], 'is_array')));

        $workouts = array_map(fn (array $workout): array => [
            'name' => $workout['name'] ?? ($workout['workoutActivityType'] ?? null),
            'start' => $workout['start'] ?? null,
            'end' => $workout['end'] ?? null,
            'fields' => array_keys($workout),
        ], array_values(array_filter($data['workouts'] ?? [], 'is_array')));

        return [
            'top_level_keys' => array_keys($payload),
            'data_keys' => is_array($data) ? array_keys($data) : [],
            'metric_count' => count($metrics),
            'metrics' => $metrics,
            'workout_count' => count($workouts),
            'workouts' => $workouts,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=HealthPayloadSummaryTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Health/HealthPayloadSummary.php tests/Unit/HealthPayloadSummaryTest.php
git commit -m "refactor: extract HealthPayloadSummary helper"
```

---

### Task 4: `ProcessHealthExport` queued job

Route a decoded payload to the registered processors, isolating per-processor failures and retrying the whole payload on failure.

**Files:**
- Create: `app/Jobs/ProcessHealthExport.php`
- Test: `tests/Feature/Health/ProcessHealthExportTest.php`

**Interfaces:**
- Consumes: `SleepProcessor`, `HeartRateProcessor` (Tasks 1-2), `HealthProcessor::handles()/process()`.
- Produces: `ProcessHealthExport` with constructor `__construct(public array $payload)`, `handle(): void`, `public int $tries = 3`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Jobs\ProcessHealthExport;
use App\Models\Sleep;

it('routes sleep_analysis to the sleep processor when the job runs', function () {
    $payload = ['data' => ['metrics' => [[
        'name' => 'sleep_analysis',
        'data' => [
            ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 03:00:00 +0000'],
            ['value' => 'REM',  'source' => 'Oura', 'start' => '2026-01-11 03:00:00 +0000', 'end' => '2026-01-11 07:00:00 +0000'],
        ],
    ]]]];

    (new ProcessHealthExport($payload))->handle();

    expect(Sleep::query()->count())->toBe(1);
});

it('ignores unknown metric names without error', function () {
    $payload = ['data' => ['metrics' => [['name' => 'mindfulness', 'data' => [['value' => 1]]]]]];

    (new ProcessHealthExport($payload))->handle();

    expect(Sleep::query()->count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ProcessHealthExportTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Create the job**

```php
<?php

namespace App\Jobs;

use App\Support\Health\HealthProcessor;
use App\Support\Health\HeartRateProcessor;
use App\Support\Health\SleepProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProcessHealthExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(): void
    {
        /** @var list<HealthProcessor> $processors */
        $processors = [app(SleepProcessor::class), app(HeartRateProcessor::class)];

        $metricNames = array_values(array_filter(array_map(
            fn ($metric): ?string => is_array($metric) ? ($metric['name'] ?? null) : null,
            $this->payload['data']['metrics'] ?? [],
        )));

        $claimed = [];
        $failures = [];

        foreach ($processors as $processor) {
            $matched = array_intersect($processor->handles(), $metricNames);

            if ($matched === []) {
                continue;
            }

            $claimed = array_merge($claimed, $matched);

            try {
                $processor->process($this->payload);
            } catch (\Throwable $exception) {
                report($exception);
                $failures[] = $processor::class;
            }
        }

        $unhandled = array_values(array_diff($metricNames, $claimed));

        if ($unhandled !== []) {
            Log::info('health.ingest unhandled metrics', ['metrics' => $unhandled]);
        }

        if ($failures !== []) {
            throw new RuntimeException('Health processors failed: '.implode(', ', $failures));
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ProcessHealthExportTest`
Expected: PASS (2 passed).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Jobs/ProcessHealthExport.php tests/Feature/Health/ProcessHealthExportTest.php
git commit -m "feat: add ProcessHealthExport job routing metrics to processors"
```

---

### Task 5: Refactor the controller (dispatch + log, no capture)

**Files:**
- Modify: `app/Http/Controllers/HealthExportController.php`
- Test: `tests/Feature/Health/HealthExportControllerTest.php`

**Interfaces:**
- Consumes: `ProcessHealthExport` (Task 4), `HealthPayloadSummary` (Task 3).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Jobs\ProcessHealthExport;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => config()->set('services.health_export.token', null));

it('dispatches the job and returns 200 for a valid payload', function () {
    Queue::fake();

    $this->postJson('/api/health/ingest', ['data' => ['metrics' => [['name' => 'sleep_analysis', 'data' => []]]]])
        ->assertOk()
        ->assertJson(['ok' => true]);

    Queue::assertPushed(ProcessHealthExport::class);
});

it('rejects a bad token with 401', function () {
    config()->set('services.health_export.token', 'secret');

    $this->postJson('/api/health/ingest', ['data' => []], ['Authorization' => 'Bearer wrong'])
        ->assertStatus(401);
});

it('rejects a non-array body with 422', function () {
    Queue::fake();

    $this->call('POST', '/api/health/ingest', [], [], [], ['CONTENT_TYPE' => 'application/json'], '"not an object"')
        ->assertStatus(422);

    Queue::assertNothingPushed();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=HealthExportControllerTest`
Expected: FAIL (still writes captures / no 422 path).

- [ ] **Step 3: Rewrite the controller**

Replace `store()`, drop `summarise()` (now in the helper), and simplify `ping()`:

```php
<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessHealthExport;
use App\Support\Health\HealthPayloadSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HealthExportController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => 'Health export endpoint ready. Send payloads with POST.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->authorised($request)) {
            return response()->json(['message' => 'Invalid or missing token.'], 401);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Body was not a JSON object.'], 422);
        }

        Log::info('health.ingest received', HealthPayloadSummary::for($payload));

        ProcessHealthExport::dispatch($payload);

        return response()->json(['ok' => true]);
    }

    private function authorised(Request $request): bool
    {
        $expected = config('services.health_export.token');

        if (! $expected) {
            return true;
        }

        $provided = $request->bearerToken() ?? $request->input('token');

        return is_string($provided) && hash_equals((string) $expected, $provided);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=HealthExportControllerTest`
Expected: PASS (3 passed).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/HealthExportController.php tests/Feature/Health/HealthExportControllerTest.php
git commit -m "feat: dispatch health ingest to queue, discard raw, log summary"
```

---

### Task 6: Command wrapper tests + delete `health:inspect`

The commands were already slimmed to processor wrappers in Tasks 1 and 2. This task locks that behaviour with command-level tests and removes the now-defunct `health:inspect`.

**Files:**
- Delete: `app/Console/Commands/Health/InspectHealthExport.php`
- Delete (if present): any test referencing `health:inspect`
- Test: `tests/Feature/Health/HealthCommandsTest.php`

**Interfaces:**
- Consumes: the slimmed `health:sleep` / `health:heart_rate` commands (Tasks 1-2).

- [ ] **Step 1: Delete `health:inspect` and find stray references**

```bash
git rm app/Console/Commands/Health/InspectHealthExport.php
grep -rn "health:inspect\|InspectHealthExport" tests app
```

Delete or update any test that referenced it (expected: none, or a dedicated inspect test to remove).

- [ ] **Step 2: Write the command wrapper tests**

```php
<?php

use App\Models\Activity;
use App\Models\Sleep;
use Illuminate\Support\Facades\File;

it('processes a supplied payload file via health:sleep --file', function () {
    $path = base_path('tests/Fixtures/health/sleep-night.json');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode(['data' => ['metrics' => [[
        'name' => 'sleep_analysis',
        'data' => [
            ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 03:00:00 +0000'],
            ['value' => 'REM',  'source' => 'Oura', 'start' => '2026-01-11 03:00:00 +0000', 'end' => '2026-01-11 07:00:00 +0000'],
        ],
    ]]]]));

    $this->artisan('health:sleep', ['--file' => $path])->assertSuccessful();

    expect(Sleep::query()->count())->toBe(1);

    File::delete($path);
});

it('prints guidance when health:sleep runs with no file and no maintenance flag', function () {
    $this->artisan('health:sleep')->assertSuccessful();
});

it('prints guidance when health:heart_rate runs with no file', function () {
    $this->artisan('health:heart_rate')->assertSuccessful();
});
```

- [ ] **Step 3: Run the command tests**

Run: `php artisan test --compact --filter=HealthCommandsTest`
Expected: PASS (commands were slimmed in Tasks 1-2, so these validate the wrappers).

- [ ] **Step 4: Run the full health suite**

Run: `php artisan test --compact --filter=Health`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "test: cover health command wrappers; drop health:inspect"
```

---

### Task 7: Full-suite verification

**Files:** none (verification only).

- [ ] **Step 1: Run the whole suite**

Run: `php artisan test --compact`
Expected: PASS. Investigate any failure referencing `health/incoming`, `summarise`, or removed command methods and fix before finishing.

- [ ] **Step 2: Grep for orphaned references**

```bash
grep -rn "health/incoming\|InspectHealthExport\|->summarise(" app tests
```

Expected: no results. Fix any stragglers, then commit if anything changed.

---

## Self-Review

**Spec coverage:** endpoint dispatch + log + 200 (Task 5); process-and-discard, no raw write (Task 5); metric routing + unknown-metric logging + per-processor isolation + retry (Task 4); SleepProcessor/HeartRateProcessor extraction reusing aggregator/matcher (Tasks 1-2); HealthPayloadSummary log line (Task 3); thin commands with `--file` + guidance + sleep maintenance modes (Task 6); delete `health:inspect` + disk-reading (Task 6); tests per the spec's testing section (Tasks 1-6); full verification (Task 7). CSV mirror retained (processors) per out-of-scope. No migration needed.

**Placeholder scan:** the two processor tasks intentionally reference "move methods verbatim" for large existing bodies rather than reproducing hundreds of lines; the exact method names and the single behavioural change (payload source; drop option reads) are specified. All new glue and all tests are shown in full.

**Type consistency:** `HealthProcessor::handles(): array` and `process(array $payload): void` used identically in Tasks 1, 2, 4. `ProcessHealthExport(public array $payload)` matches Task 5's `ProcessHealthExport::dispatch($payload)`. `SleepProcessor::scoreAll(): int` defined in Task 1, called in Task 6. `HealthPayloadSummary::for()` defined in Task 3, used in Task 5.
