<?php

use App\Enums\StravaAspect;
use App\Jobs\ProcessStravaWebhookEvent;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.strava.webhook_secret' => 'path-secret',
        'services.strava.webhook_verify_token' => 'verify-token',
    ]);
});

/** @return array<string, mixed> */
function stravaEvent(array $overrides = []): array
{
    return [
        'object_type' => 'activity',
        'object_id' => 15840000000,
        'aspect_type' => 'create',
        'owner_id' => 1234,
        'subscription_id' => 99,
        'event_time' => 1758326400,
        'updates' => [],
        ...$overrides,
    ];
}

it('echoes the challenge back to Strava', function () {
    $this->getJson('/api/strava/webhook/path-secret?hub.mode=subscribe&hub.challenge=abc123&hub.verify_token=verify-token')
        ->assertOk()
        ->assertExactJson(['hub.challenge' => 'abc123']);
});

it('refuses a handshake carrying the wrong verify token', function () {
    $this->getJson('/api/strava/webhook/path-secret?hub.mode=subscribe&hub.challenge=abc123&hub.verify_token=guessed')
        ->assertForbidden();
});

it('hides the endpoint behind the secret in the path', function () {
    $this->getJson('/api/strava/webhook/wrong?hub.mode=subscribe&hub.challenge=abc&hub.verify_token=verify-token')
        ->assertNotFound();

    $this->postJson('/api/strava/webhook/wrong', stravaEvent())->assertNotFound();
});

it('rejects everything when no secret is configured', function () {
    config(['services.strava.webhook_secret' => null]);

    $this->postJson('/api/strava/webhook/path-secret', stravaEvent())->assertNotFound();
});

// Strava allows roughly two seconds before it treats the callback as failed,
// so the read has to happen on the queue. A stray request fails the test.
it('queues the event and answers without calling Strava', function () {
    Queue::fake();

    $this->postJson('/api/strava/webhook/path-secret', stravaEvent(['aspect_type' => 'update']))
        ->assertOk();

    Queue::assertPushed(ProcessStravaWebhookEvent::class, function (ProcessStravaWebhookEvent $job): bool {
        return $job->event->aspect === StravaAspect::Update
            && $job->event->objectId === '15840000000'
            && $job->event->ownerId === '1234';
    });
});

it('accepts an object type Strava has not documented yet', function () {
    Queue::fake();

    $this->postJson('/api/strava/webhook/path-secret', stravaEvent(['object_type' => 'segment']))
        ->assertOk();

    Queue::assertPushed(ProcessStravaWebhookEvent::class);
});
