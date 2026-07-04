<?php

use App\Models\Note;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');
});

it('filters notes by from and to dates inclusively', function () {
    Note::factory()->create(['occurred_at' => '2026-06-01 08:00:00']);
    Note::factory()->create(['occurred_at' => '2026-06-15 08:00:00']);
    Note::factory()->create(['occurred_at' => '2026-07-01 08:00:00']);

    $this->withToken('test-token')->getJson('/api/v1/notes?from=2026-06-10&to=2026-06-30')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('respects per_page and caps at 100', function () {
    Note::factory()->count(3)->create();

    $this->withToken('test-token')->getJson('/api/v1/notes?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2);

    $this->withToken('test-token')->getJson('/api/v1/notes?per_page=500')->assertUnprocessable();
});

it('rejects a from date after the to date', function () {
    $this->withToken('test-token')->getJson('/api/v1/notes?from=2026-07-01&to=2026-06-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from']);
});

it('filters by from alone', function () {
    Note::factory()->create(['occurred_at' => '2026-06-01 08:00:00']);
    Note::factory()->create(['occurred_at' => '2026-07-01 08:00:00']);

    $this->withToken('test-token')->getJson('/api/v1/notes?from=2026-06-10')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by to alone', function () {
    Note::factory()->create(['occurred_at' => '2026-06-01 08:00:00']);
    Note::factory()->create(['occurred_at' => '2026-07-01 08:00:00']);

    $this->withToken('test-token')->getJson('/api/v1/notes?to=2026-06-10')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
