<?php

use App\Models\Activity;
use App\Models\Project;

use function Pest\Laravel\get;

/**
 * Which entries end with a place to respond. Every type carries one whether or
 * not anybody has responded, projects included: a project is standing content
 * like a page, and the likeliest thing here for somebody to link to.
 */
it('offers a way to respond to a record nobody has responded to', function () {
    $activity = Activity::factory()->create();

    get($activity->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has('conversation.reactions'));
});

it('takes responses on a project, which is what people bookmark', function () {
    $project = Project::factory()->create();

    get($project->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has('conversation.reactions'));
});
