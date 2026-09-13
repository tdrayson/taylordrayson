<?php

use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Subject;
use App\Models\User;
use App\Queries\Lookups\SubjectLookup;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('tags a synced entry that has no authoring fields', function (string $type, Closure $factory) {
    $entry = $factory();
    $subject = Subject::factory()->person()->create();

    actingAs(User::factory()->create())->post("/entries/{$type}/{$entry->id}/subjects", [
        'subjects' => [$subject->id],
    ])->assertRedirect();

    expect($entry->fresh()->subjects)->toHaveCount(1);
})->with([
    'a Strava activity' => ['activity', fn () => Activity::factory()->create()],
    'a Swarm check-in' => ['checkin', fn () => Checkin::factory()->create()],
]);

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

it('lines up a person under "With" but renders a thing on no line at all', function () {
    $activity = Activity::factory()->create();
    $person = Subject::factory()->person()->create(['name' => 'Clare']);
    $thing = Subject::factory()->thing()->create(['name' => 'Old Bike']);
    $activity->subjects()->attach([$person->id, $thing->id]);

    get($activity->url())->assertInertia(fn ($page) => $page
        ->where('subjects.lines', fn ($lines): bool => count($lines) === 1
            && $lines[0]['phrase'] === 'With'
            && $lines[0]['subjects'][0]['name'] === 'Clare')
        ->where('subjects.direct', fn ($direct): bool => $direct->pluck('name')->sort()->values()->all() === ['Clare', 'Old Bike']));
});

it('returns the new subject as json for the entry picker\'s create shortcut', function () {
    actingAs(User::factory()->create())
        ->postJson('/subjects', ['kind' => 'person', 'name' => 'Bear'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Bear')
        ->assertJsonPath('data.kind', 'Person')
        ->assertJsonStructure(['data' => ['id', 'name', 'kind']]);
});
