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
