<?php

use Illuminate\Support\Facades\Storage;

it('captures a health export payload and stores raw plus summary', function () {
    Storage::fake('local');
    config(['services.health_export.token' => 'secret']);

    $payload = [
        'data' => [
            'metrics' => [
                [
                    'name' => 'heart_rate',
                    'units' => 'count/min',
                    'data' => [
                        ['date' => '2026-06-29 08:00:00 +0100', 'source' => 'Apple Watch', 'Avg' => 72, 'Min' => 70, 'Max' => 75],
                    ],
                ],
            ],
            'workouts' => [],
        ],
    ];

    $response = $this->withToken('secret')->postJson('/api/health/ingest', $payload);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('summary.metric_count', 1)
        ->assertJsonPath('summary.metrics.0.name', 'heart_rate')
        ->assertJsonPath('summary.metrics.0.points', 1);

    expect(Storage::disk('local')->files('health/incoming'))->toHaveCount(2);
});

it('rejects a payload without a valid token', function () {
    Storage::fake('local');
    config(['services.health_export.token' => 'secret']);

    $this->postJson('/api/health/ingest', ['data' => []])->assertStatus(401);

    expect(Storage::disk('local')->files('health/incoming'))->toBeEmpty();
});

it('accepts any payload when no token is configured', function () {
    Storage::fake('local');
    config(['services.health_export.token' => null]);

    $this->postJson('/api/health/ingest', ['data' => ['metrics' => []]])->assertOk();
});
