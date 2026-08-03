<?php

use App\Jobs\ProcessHealthExport;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => config()->set('services.api.token', 'test-token'));

it('dispatches the job and returns 200 for a valid payload', function () {
    Queue::fake();

    $this->withToken('test-token')
        ->postJson('/api/v1/health-export', ['data' => ['metrics' => [['name' => 'sleep_analysis', 'data' => []]]]])
        ->assertOk()
        ->assertJsonPath('data.ok', true)
        ->assertJsonPath('data.metrics.0.name', 'sleep_analysis');

    Queue::assertPushed(ProcessHealthExport::class);
});

it('rejects an unauthenticated post', function () {
    Queue::fake();

    $this->postJson('/api/v1/health-export', ['data' => ['metrics' => []]])->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('rejects a bad token with 401', function () {
    Queue::fake();

    $this->withToken('wrong')->postJson('/api/v1/health-export', ['data' => []])->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('rejects a non-array body with 422', function () {
    Queue::fake();

    $this->call('POST', '/api/v1/health-export', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer test-token',
    ], '"not an object"')
        ->assertStatus(422);

    Queue::assertNothingPushed();
});

it('confirms reachability on GET', function () {
    $this->withToken('test-token')->getJson('/api/v1/health-export')
        ->assertOk()
        ->assertJsonPath('data.ok', true);
});

it('tells a phone still on the old path where the endpoint went', function () {
    Queue::fake();

    $this->postJson('/api/health/ingest', ['data' => ['metrics' => []]])
        ->assertStatus(410)
        ->assertJsonPath('message', 'Moved to POST /api/v1/health-export, with an Authorization: Bearer <token> header.');

    Queue::assertNothingPushed();
});
