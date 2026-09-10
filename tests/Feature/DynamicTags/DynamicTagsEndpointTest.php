<?php

use App\DynamicTags\DynamicTagRegistry;
use App\Models\Note;
use App\Models\User;

it('is closed to guests', function () {
    $this->getJson('/dynamic-tags')->assertUnauthorized();
});

it('lists every registered tag with its schema', function () {
    $response = $this->actingAs(User::factory()->create())->getJson('/dynamic-tags');

    $response->assertOk();

    $tags = collect($response->json('data'));
    $count = $tags->firstWhere('name', 'entries.count');
    $registered = count(app(DynamicTagRegistry::class)->all());

    expect($tags)->toHaveCount($registered)
        ->and($count)->not->toBeNull()
        ->and($count['group'])->toBe('Entries')
        ->and($count['supports'])->toBe(['inline'])
        ->and(collect($count['options'])->pluck('name'))->toContain('type', 'period');
});

it('carries a preview of each tag current value', function () {
    Note::factory()->count(3)->create();

    $preview = collect($this->actingAs(User::factory()->create())->getJson('/dynamic-tags')->json('data'))
        ->firstWhere('name', 'entries.count')['preview'];

    expect($preview)->toBe('3');
});

it('reports the placements a tag is legal in', function () {
    $tags = collect($this->actingAs(User::factory()->create())->getJson('/dynamic-tags')->json('data'));

    expect($tags->firstWhere('name', 'entries.photo')['supports'])->toBe(['image'])
        ->and($tags->firstWhere('name', 'site.social')['supports'])->toBe(['inline', 'href']);
});
