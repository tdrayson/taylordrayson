<?php

use App\Models\Activity;
use App\Models\Project;

use function Pest\Laravel\get;

/**
 * Which entries end with a place to respond. Every type carries one whether or
 * not anybody has responded; a project is the one that takes none at all.
 */
it('offers a way to respond to a record nobody has responded to', function () {
    $activity = Activity::factory()->create();

    get($activity->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has('conversation.reactions'));
});

it('takes no responses on a project', function () {
    $project = Project::factory()->create();

    get($project->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('conversation', null));
});
