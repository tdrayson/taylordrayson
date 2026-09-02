<?php

use App\Models\Activity;
use App\Models\Subject;

use function Pest\Laravel\get;

function subjectSearchUrl(array $filter): string
{
    return '/search?'.http_build_query(['filter' => json_encode($filter)]);
}

it('finds entries by a subject reached through a photograph', function () {
    $subject = Subject::factory()->person()->create(['slug' => 'clare']);
    $activity = Activity::factory()->create();
    $attachment = $activity->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $attachment->subjects()->attach($subject, ['role' => 'subject', 'x' => 5, 'y' => 5]);

    get(subjectSearchUrl([[
        'type' => 'any',
        'conditions' => [['field' => 'person', 'operator' => 'includes', 'value' => 'clare']],
    ]]))->assertOk()->assertInertia(fn ($page) => $page->has('groups', 1));
});

it('requires every subject for includes_all', function () {
    $bear = Subject::factory()->pet()->create(['slug' => 'bear']);
    Subject::factory()->pet()->create(['slug' => 'beanie']);
    Activity::factory()->create()->subjects()->attach($bear);

    get(subjectSearchUrl([[
        'type' => 'any',
        'conditions' => [['field' => 'pet', 'operator' => 'includes_all', 'value' => ['bear', 'beanie']]],
    ]]))->assertInertia(fn ($page) => $page->has('groups', 0));
});

it('does not quietly drop entries with no subjects at all', function () {
    // Pinned to one day: the search groups results by day, and the brief's
    // premise (three activities collapse to one group) only holds if the
    // factory's random occurred_at doesn't scatter them across different days.
    Activity::factory()->count(3)->create(['occurred_at' => now()]);

    get(subjectSearchUrl([[
        'type' => 'any',
        'conditions' => [['field' => 'person', 'operator' => 'has_none', 'value' => null]],
    ]]))->assertInertia(fn ($page) => $page->has('groups', 1));
});

it('excludes entries carrying the given subject', function () {
    $bear = Subject::factory()->pet()->create(['slug' => 'bear']);
    Activity::factory()->create(['occurred_at' => now()])->subjects()->attach($bear);
    Activity::factory()->create(['occurred_at' => now()->subDay()]);

    get(subjectSearchUrl([[
        'type' => 'any',
        'conditions' => [['field' => 'pet', 'operator' => 'excludes', 'value' => 'bear']],
    ]]))->assertInertia(fn ($page) => $page->has('groups', 1));
});

it('has_any matches an entry carrying any subject of that kind', function () {
    $bear = Subject::factory()->pet()->create();
    Activity::factory()->create(['occurred_at' => now()])->subjects()->attach($bear);
    Activity::factory()->create(['occurred_at' => now()->subDay()]);

    get(subjectSearchUrl([[
        'type' => 'any',
        'conditions' => [['field' => 'pet', 'operator' => 'has_any', 'value' => null]],
    ]]))->assertInertia(fn ($page) => $page->has('groups', 1));
});

it('scopes a subject field to its own kind, not just the shared slug', function () {
    // Subjects are unique on (kind, slug), not slug alone, so a person and a
    // pet can share one: "People includes bella" must not also match the pet.
    $person = Subject::factory()->person()->create(['slug' => 'bella']);
    $pet = Subject::factory()->pet()->create(['slug' => 'bella']);

    Activity::factory()->create(['occurred_at' => now()])->subjects()->attach($person);
    Activity::factory()->create(['occurred_at' => now()->subDay()])->subjects()->attach($pet);

    get(subjectSearchUrl([[
        'type' => 'any',
        'conditions' => [['field' => 'person', 'operator' => 'includes', 'value' => 'bella']],
    ]]))->assertInertia(fn ($page) => $page->has('groups', 1));
});
