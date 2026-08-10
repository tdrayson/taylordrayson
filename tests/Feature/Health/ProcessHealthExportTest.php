<?php

use App\Jobs\ProcessHealthExport;
use App\Models\Activity;
use App\Models\Sleep;
use App\Support\Health\HealthProcessor;
use App\Support\Health\HeartRateMatcher;
use App\Support\Health\HeartRateProcessor;
use App\Support\Health\SleepAggregator;
use App\Support\Health\SleepProcessor;
use App\Support\Health\SleepScore;
use Illuminate\Support\Facades\Log;

/**
 * Bind a processor subclass that appends a nonexistent temp CSV path, keeping
 * mirrorToCsv()/scoreAll() on their no-op branch so the tracked data/sleep.csv is
 * never opened, while the job's single-argument call-site stays untouched.
 */
function bindSleepProcessorWithTempCsv(): string
{
    $csvPath = sys_get_temp_dir().'/sleep-test-'.uniqid().'.csv';

    app()->instance(SleepProcessor::class, new class(app(SleepAggregator::class), app(SleepScore::class), $csvPath) extends SleepProcessor
    {
        public function __construct(SleepAggregator $aggregator, SleepScore $scorer, private readonly string $csvPath)
        {
            parent::__construct($aggregator, $scorer);
        }

        // Signature matches the parent exactly (LSP requires it), but the
        // incoming $csvPath is always ignored in favour of the bound temp
        // path, since the job's call-site only ever passes $payload.
        public function process(array $payload, ?string $csvPath = null): void
        {
            parent::process($payload, $this->csvPath);
        }
    });

    return $csvPath;
}

/**
 * Same approach as bindSleepProcessorWithTempCsv(), for HeartRateProcessor:
 * a unique, nonexistent temp CSV path stands in for the tracked
 * data/activities.csv so mirrorToCsv() no-ops instead of touching it.
 */
function bindHeartRateProcessorWithTempCsv(): string
{
    $csvPath = sys_get_temp_dir().'/activities-test-'.uniqid().'.csv';

    app()->instance(HeartRateProcessor::class, new class(app(HeartRateMatcher::class), $csvPath) extends HeartRateProcessor
    {
        public function __construct(HeartRateMatcher $matcher, private readonly string $csvPath)
        {
            parent::__construct($matcher);
        }

        // 240 mirrors HeartRateProcessor::MAX_POINTS, which is private and so
        // not visible to this subclass; it isn't exercised by these tests
        // either way, since the job's call-site never passes $cap.
        public function process(array $payload, ?string $csvPath = null, bool $overwrite = false, int $cap = 240): void
        {
            parent::process($payload, $this->csvPath, $overwrite, $cap);
        }
    });

    return $csvPath;
}

it('routes sleep_analysis to the sleep processor when the job runs', function () {
    bindSleepProcessorWithTempCsv();

    $payload = ['data' => ['metrics' => [[
        'name' => 'sleep_analysis',
        'data' => [
            ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 03:00:00 +0000'],
            ['value' => 'REM', 'source' => 'Oura', 'start' => '2026-01-11 03:00:00 +0000', 'end' => '2026-01-11 07:00:00 +0000'],
        ],
    ]]]];

    (new ProcessHealthExport($payload))->handle();

    expect(Sleep::query()->count())->toBe(1);
});

it('ignores unknown metric names without error', function () {
    Log::spy();

    $payload = ['data' => ['metrics' => [['name' => 'mindfulness', 'data' => [['value' => 1]]]]]];

    (new ProcessHealthExport($payload))->handle();

    expect(Sleep::query()->count())->toBe(0);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('health.export unhandled metrics', ['metrics' => ['mindfulness']]);
});

it('isolates a failing processor, still runs the others, and throws to trigger a retry', function () {
    // A fake processor standing in for the sleep processor: it claims
    // sleep_analysis but always throws, so we can prove the job isolates the
    // failure and keeps going, without ever touching the real SleepProcessor
    // (and therefore never touching the real data/sleep.csv).
    $failingProcessor = new class implements HealthProcessor
    {
        public function handles(): array
        {
            return ['sleep_analysis'];
        }

        public function process(array $payload): void
        {
            throw new RuntimeException('boom');
        }
    };

    app()->instance(SleepProcessor::class, $failingProcessor);
    bindHeartRateProcessorWithTempCsv();

    // Falls inside the heart_rate sample's match window below, so the
    // HeartRateProcessor update is directly observable even though Sleep
    // throws first.
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-01-10 09:00:00',
        'duration' => 1800,
        'timezone' => null,
        'altitude' => null,
        'average_heart_rate' => null,
        'max_heart_rate' => null,
    ]);

    $payload = ['data' => ['metrics' => [
        ['name' => 'sleep_analysis', 'data' => [['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 03:00:00 +0000']]],
        ['name' => 'heart_rate', 'data' => [['start' => '2026-01-10 09:05:00 +0000', 'Avg' => 130, 'Max' => 150, 'source' => 'Apple Watch']]],
    ]]];

    expect(fn () => (new ProcessHealthExport($payload))->handle())->toThrow(RuntimeException::class);

    $activity->refresh();

    expect($activity->average_heart_rate)->toBe(130)
        ->and($activity->max_heart_rate)->toBe(150);
});
