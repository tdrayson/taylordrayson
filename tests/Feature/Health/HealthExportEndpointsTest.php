<?php

use App\Jobs\ProcessHealthExport;
use App\Support\Health\HeartRateProcessor;
use App\Support\Health\SleepProcessor;
use App\Support\StateStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => config()->set('services.api.token', 'test-token'));

function metric(string $name, array $samples = []): array
{
    return ['data' => ['metrics' => [['name' => $name, 'data' => $samples]]]];
}

function localDay(string $time = '00:00:00'): string
{
    return Carbon::now(config('app.home_timezone'))->format('Y-m-d')." {$time} +0100";
}

it('queues each domain against its own processor', function (string $path, string $name, string $processor) {
    Queue::fake();

    test()->withToken('test-token')
        ->postJson("/api/v1/health-export/{$path}", metric($name, [['qty' => 1, 'date' => localDay()]]))
        ->assertOk()
        ->assertJsonPath('data.queued', true)
        ->assertJsonPath("data.received.{$name}", 1);

    Queue::assertPushed(ProcessHealthExport::class, fn (ProcessHealthExport $job): bool => $job->processor === $processor);
})->with([
    ['sleep', 'sleep_analysis', SleepProcessor::class],
    ['heart-rate', 'heart_rate', HeartRateProcessor::class],
]);

it('rejects a metric the endpoint does not own', function () {
    Queue::fake();

    $this->withToken('test-token')
        ->postJson('/api/v1/health-export/sleep', metric('heart_rate', [['qty' => 60, 'date' => localDay()]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('data.metrics.0.name');

    Queue::assertNothingPushed();
});

it('rejects an unauthenticated post', function () {
    $this->postJson('/api/v1/health-export/activity-rings', metric('step_count'))->assertUnauthorized();
});

it('sums activity metrics into the rings state and leaves the goals alone', function () {
    app(StateStore::class)->merge('now.rings', ['move' => 128, 'move_goal' => 250, 'exercise_goal' => 90, 'stand_goal' => 12]);

    $payload = ['data' => ['metrics' => [
        ['name' => 'active_energy', 'data' => [['qty' => 20.4, 'date' => localDay()], ['qty' => 34.1, 'date' => localDay('11:00:00')]]],
        ['name' => 'step_count', 'data' => [['qty' => 497, 'date' => localDay()]]],
        ['name' => 'apple_stand_hour', 'data' => [['qty' => 5, 'date' => localDay()]]],
        ['name' => 'apple_exercise_time', 'data' => []],
    ]]];

    $this->withToken('test-token')
        ->postJson('/api/v1/health-export/activity-rings', $payload)
        ->assertOk()
        ->assertJsonPath('data.rings.move', 55)
        ->assertJsonPath('data.rings.steps', 497)
        ->assertJsonPath('data.rings.stand', 5)
        // Sent with no samples because the day has not touched the ring yet,
        // which is an honest zero rather than an absent reading.
        ->assertJsonPath('data.rings.exercise', 0);

    expect(app(StateStore::class)->get('now.rings'))
        ->toBe(['move' => 55, 'move_goal' => 250, 'exercise_goal' => 90, 'stand_goal' => 12, 'steps' => 497, 'stand' => 5, 'exercise' => 0]);
});

it('drops samples from a day that is not today', function () {
    $yesterday = Carbon::now(config('app.home_timezone'))->subDay()->format('Y-m-d').' 00:00:00 +0100';

    $this->withToken('test-token')
        ->postJson('/api/v1/health-export/activity-rings', metric('step_count', [['qty' => 11240, 'date' => $yesterday]]))
        ->assertOk()
        ->assertJsonPath('data.rings.steps', 0);
});

it('confirms reachability and names what it accepts', function (string $path, string $metric) {
    test()->withToken('test-token')->getJson("/api/v1/health-export/{$path}")
        ->assertOk()
        ->assertJsonPath('data.ok', true)
        ->assertJsonFragment(['accepts' => [$metric]]);
})->with([
    ['sleep', 'sleep_analysis'],
    ['heart-rate', 'heart_rate'],
]);
