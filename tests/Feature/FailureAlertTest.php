<?php

use App\Listeners\AlertOnFailedJob;
use App\Services\Pushover\Client as PushoverClient;
use App\Support\FailureAlert;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config(['services.pushover.token' => 'app-token', 'services.pushover.user' => 'user-key']);
    Cache::flush();
    Saloon::fake(['*' => MockResponse::make('', 200)]);
});

/** Built through the real scheduler, so the command string is the one it writes. */
function scheduledTask(string $command): ScheduledEvent
{
    return app(Schedule::class)->command($command);
}

it('pushes an alert when a scheduled command fails', function () {
    event(new ScheduledTaskFailed(scheduledTask('podcast:sync'), new RuntimeException('exit code 1')));

    Saloon::assertSent(function ($request, $response) {
        $body = $request->body()->all();

        return $response->getPendingRequest()->getUrl() === 'https://api.pushover.net/1/messages.json'
            && $body['title'] === 'Scheduled command failed'
            && str_contains($body['message'], 'podcast:sync')
            && str_contains($body['message'], 'exit code 1');
    });
});

it('pushes an alert when a queued job fails', function () {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\ResolveLinkFavicons');

    (new AlertOnFailedJob(app(FailureAlert::class)))
        ->handle(new JobFailed('database', $job, new RuntimeException('timed out')));

    Saloon::assertSent(fn ($request, $response) => $request->body()->all()['title'] === 'Queued job failed'
        && str_contains($request->body()->all()['message'], 'ResolveLinkFavicons'));
});

// The whole point of the throttle: trakt:sync runs every minute, so an
// unthrottled alert would be 1,440 notifications a day and muted within one.
it('sends once per failing thing per hour, however often it fails', function () {
    foreach (range(1, 20) as $ignored) {
        event(new ScheduledTaskFailed(scheduledTask('trakt:sync'), new RuntimeException('down')));
    }

    Saloon::assertSentCount(1);
});

it('still alerts on a different command inside the same window', function () {
    event(new ScheduledTaskFailed(scheduledTask('trakt:sync'), new RuntimeException('down')));
    event(new ScheduledTaskFailed(scheduledTask('strava:sync'), new RuntimeException('down')));

    Saloon::assertSentCount(2);
});

it('does nothing when no credentials are configured', function () {
    config(['services.pushover.token' => null, 'services.pushover.user' => null]);

    event(new ScheduledTaskFailed(scheduledTask('podcast:sync'), new RuntimeException('down')));

    Saloon::assertNothingSent();
});

it('registers both listeners', function () {
    expect(Event::hasListeners(ScheduledTaskFailed::class))->toBeTrue()
        ->and(Event::hasListeners(JobFailed::class))->toBeTrue();
});

/**
 * The one client that fires on an unhappy path, so a test that trips an alert
 * without faking anything must not reach a real phone. phpunit.xml blanks the
 * credentials; send() returns before making a request when it has none.
 */
it('cannot notify a real device when the suite has no credentials', function () {
    config(['services.pushover.token' => env('PUSHOVER_TOKEN'), 'services.pushover.user' => env('PUSHOVER_USER')]);

    expect(config('services.pushover.token'))->toBeEmpty()
        ->and(config('services.pushover.user'))->toBeEmpty()
        ->and(app(PushoverClient::class)->send('Scheduled command failed', 'podcast:sync'))->toBeFalse();
});
