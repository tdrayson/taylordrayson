<?php

use App\Actions\Strava\StoreStravaActivity;
use App\Data\StravaWebhookEvent;
use App\Enums\EntryStatus;
use App\Jobs\ProcessStravaWebhookEvent;
use App\Models\Activity;
use App\Services\Pushover\Client as Pushover;
use App\Services\Strava\Client;
use Illuminate\Support\Facades\Bus;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config([
        'services.strava.client_id' => 'cid',
        'services.strava.client_secret' => 'secret',
        'services.strava.refresh_token' => 'refresh',
        'services.strava.athlete_id' => '1234',
    ]);

    // The map is drawn from the stored polyline on its own queue; these tests
    // are about the row, not the picture.
    Bus::fake();
});

/** @param  array<string, mixed>  $overrides */
function webhookEvent(array $overrides = []): StravaWebhookEvent
{
    return StravaWebhookEvent::fromPayload([
        'object_type' => 'activity',
        'object_id' => '555',
        'aspect_type' => 'create',
        'owner_id' => '1234',
        'subscription_id' => 99,
        'event_time' => 1758326400,
        'updates' => [],
        ...$overrides,
    ]);
}

/** @param  array<string, mixed>  $overrides */
function fakeStravaDetail(array $overrides = []): void
{
    Saloon::fake([
        'oauth/token*' => MockResponse::make(['access_token' => 'tok', 'expires_in' => 3600]),
        '*/activities/555*' => MockResponse::make([
            'id' => 555,
            'name' => 'Morning Run',
            'description' => 'Felt good',
            'sport_type' => 'Run',
            'start_date_local' => '2026-09-18T07:15:00Z',
            'moving_time' => 1800,
            'elapsed_time' => 1850,
            'distance' => 5012.4,
            'timezone' => '(GMT+00:00) Europe/London',
            'total_photo_count' => 0,
            ...$overrides,
        ]),
    ]);
}

it('stores the activity a create event names', function () {
    fakeStravaDetail();

    (new ProcessStravaWebhookEvent(webhookEvent()))->handle(app(Client::class), app(StoreStravaActivity::class));

    $activity = Activity::sole();

    expect($activity->source_id)->toBe('555')
        ->and($activity->name)->toBe('Morning Run')
        ->and($activity->description)->toBe('Felt good')
        ->and($activity->type)->toBe('run')
        ->and($activity->distance)->toBe(5012)
        ->and($activity->occurred_at->format('Y-m-d H:i'))->toBe('2026-09-18 07:15');
});

it('brings the stored row back in line on an update event', function () {
    $activity = Activity::factory()->create([
        'source' => 'strava',
        'source_id' => '555',
        'name' => 'Afternoon Run',
        'description' => null,
    ]);

    fakeStravaDetail(['name' => 'Renamed by hand']);

    (new ProcessStravaWebhookEvent(webhookEvent(['aspect_type' => 'update'])))
        ->handle(app(Client::class), app(StoreStravaActivity::class));

    expect(Activity::count())->toBe(1)
        ->and($activity->refresh()->name)->toBe('Renamed by hand')
        ->and($activity->description)->toBe('Felt good');
});

// Private rather than gone: a delete on Strava is also what an activity turned
// "Only You" looks like, and either way the photos and map are worth keeping.
it('makes the activity private on a delete event', function () {
    $activity = Activity::factory()->create([
        'source' => 'strava',
        'source_id' => '555',
        'status' => EntryStatus::Published,
    ]);

    (new ProcessStravaWebhookEvent(webhookEvent(['aspect_type' => 'delete'])))
        ->handle(app(Client::class), app(StoreStravaActivity::class));

    expect($activity->refresh()->status)->toBe(EntryStatus::Private);
});

it('leaves another athlete alone', function () {
    (new ProcessStravaWebhookEvent(webhookEvent(['owner_id' => '9999'])))
        ->handle(app(Client::class), app(StoreStravaActivity::class));

    expect(Activity::count())->toBe(0);
});

it('puts a deauthorisation on the phone', function () {
    $pushover = Mockery::mock(Pushover::class);
    $pushover->shouldReceive('send')->once()->withArgs(fn (string $title): bool => str_contains($title, 'revoked'));
    app()->instance(Pushover::class, $pushover);

    (new ProcessStravaWebhookEvent(webhookEvent([
        'object_type' => 'athlete',
        'aspect_type' => 'update',
        'updates' => ['authorized' => 'false'],
    ])))->handle(app(Client::class), app(StoreStravaActivity::class));

    expect(Activity::count())->toBe(0);
});

// Strava pushes create before the upload has finished processing, so the first
// read 404s often enough that failing here would lose activities outright.
it('retries when Strava has not published the activity yet', function () {
    Saloon::fake([
        'oauth/token*' => MockResponse::make(['access_token' => 'tok', 'expires_in' => 3600]),
        '*/activities/555*' => MockResponse::make(['message' => 'Resource Not Found'], 404),
    ]);

    $job = Mockery::mock(ProcessStravaWebhookEvent::class, [webhookEvent()])->makePartial();
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('release')->once()->with(30);

    $job->handle(app(Client::class), app(StoreStravaActivity::class));

    expect(Activity::count())->toBe(0);
});
