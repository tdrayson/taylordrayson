<?php

use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-03-10 12:00:00'));
afterEach(fn () => CarbonImmutable::setTestNow());

it('is closed to guests', function () {
    $this->getJson('/dynamic-tags/resolve-date?value=today')->assertUnauthorized();
});

it('resolves free text to an absolute date, the same grammar Period::parse applies', function () {
    $response = $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/resolve-date?value=1 march 2019');

    $response->assertOk()->assertJsonPath('data.date', '2019-03-01');
});

it('resolves a relative phrase against the current date', function () {
    $response = $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/resolve-date?value=last week');

    $response->assertOk()->assertJsonPath('data.date', '2026-03-02');
});

it('degrades an unparseable string to null instead of erroring', function () {
    $response = $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/resolve-date?value=not a date at all');

    $response->assertOk()->assertJsonPath('data.date', null);
});

it('requires a value', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/resolve-date')
        ->assertInvalid('value');
});
