<?php

use App\Models\Note;
use App\Models\User;

it('is closed to guests', function () {
    $this->getJson('/dynamic-tags/preview?name=entries.count')->assertUnauthorized();
});

it('resolves a tag against an arbitrary option set, not just its defaults', function () {
    Note::factory()->count(3)->create();

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.count&options[type]=note');

    $response->assertOk()->assertJsonPath('data.preview', '3');
});

it('rejects a tag name the registry does not know', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.nope')
        ->assertInvalid('name');
});

it('rejects an option key the tag does not declare', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.count&options[bogus]=1')
        ->assertInvalid('options.bogus');
});

it('rejects a value outside the option\'s declared choices', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.count&options[type]=bogus')
        ->assertInvalid('options.type');
});

it('accepts a bare four-digit year as a period', function () {
    Note::factory()->create(['occurred_at' => '2019-06-01 12:00:00']);
    Note::factory()->create(['occurred_at' => '2023-06-01 12:00:00']);

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/dynamic-tags/preview?name=entries.count&options[period]=2019');

    $response->assertOk()->assertJsonPath('data.preview', '1');
});
