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
