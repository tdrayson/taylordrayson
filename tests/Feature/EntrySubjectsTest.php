<?php

use App\Models\Activity;
use App\Models\Subject;
use App\Models\User;
use App\Queries\Lookups\SubjectLookup;

use function Pest\Laravel\actingAs;

it('tags a synced entry that has no authoring fields', function () {
    $activity = Activity::factory()->create();
    $subject = Subject::factory()->person()->create();

    actingAs(User::factory()->create())->post("/entries/activity/{$activity->id}/subjects", [
        'subjects' => [$subject->id],
    ])->assertRedirect();

    expect($activity->fresh()->subjects)->toHaveCount(1);
});

it('replaces an entry\'s subjects wholesale', function () {
    $activity = Activity::factory()->create();
    $first = Subject::factory()->person()->create();
    $second = Subject::factory()->person()->create();
    $activity->subjects()->attach($first);

    actingAs(User::factory()->create())->post("/entries/activity/{$activity->id}/subjects", [
        'subjects' => [$second->id],
    ]);

    expect($activity->fresh()->subjects->pluck('id')->all())->toBe([$second->id]);
});

it('keeps Self out of the entry picker but offers it for a photograph', function () {
    Subject::factory()->person()->create([
        'name' => 'Taylor Drayson', 'slug' => config('life.self_slug'),
    ]);

    $lookup = app(SubjectLookup::class);

    expect($lookup('Taylor'))->toHaveCount(0)
        ->and($lookup('Taylor', includeSelf: true))->toHaveCount(1);
});

it('ranks an established subject above an unused one', function () {
    $used = Subject::factory()->person()->create(['name' => 'Clare Adams']);
    Subject::factory()->person()->create(['name' => 'Clare Byrne']);
    Activity::factory()->create()->subjects()->attach($used);

    $results = app(SubjectLookup::class)('Clare');

    expect($results[0]['label'])->toBe('Clare Adams');
});
