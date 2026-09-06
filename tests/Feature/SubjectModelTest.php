<?php

use App\Enums\SubjectKind;
use App\Models\Subject;

it('scopes slug uniqueness to the kind', function () {
    Subject::factory()->create(['kind' => SubjectKind::Person, 'slug' => 'bella']);
    Subject::factory()->create(['kind' => SubjectKind::Pet, 'slug' => 'bella']);

    expect(Subject::query()->where('slug', 'bella')->count())->toBe(2);
});

it('builds its url from the kind segment', function () {
    $subject = Subject::factory()->create(['kind' => SubjectKind::Spot, 'slug' => 'the-harrow']);

    expect($subject->url())->toBe('/life/spots/the-harrow');
});

it('drops a fact missing either half', function () {
    $subject = Subject::factory()->create(['meta' => [
        ['label' => 'Cost', 'value' => '8500'],
        ['label' => '', 'value' => 'orphan'],
    ]]);

    expect($subject->refresh()->meta->facts)->toHaveCount(1);
});
