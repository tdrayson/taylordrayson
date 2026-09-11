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

it('tells apart a preview with nothing to resolve from one with no option chosen', function () {
    $tags = collect($this->actingAs(User::factory()->create())->getJson('/dynamic-tags')->json('data'));

    // site.social has no default network, so it can never resolve without one.
    expect($tags->firstWhere('name', 'site.social')['preview'])->toBe('Not set')
        ->and($tags->firstWhere('name', 'site.social')['previewResolved'])->toBeFalse()
        // ambient.rings.steps has a default (empty) option set but no ambient
        // state has ever been written in this test, so its source is empty.
        ->and($tags->firstWhere('name', 'ambient.rings.steps')['preview'])->toBe('No data')
        ->and($tags->firstWhere('name', 'ambient.rings.steps')['previewResolved'])->toBeFalse()
        ->and($tags->firstWhere('name', 'entries.count')['previewResolved'])->toBeTrue();
});
