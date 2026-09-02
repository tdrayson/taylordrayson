<?php

use App\Models\Activity;
use App\Models\Article;
use App\Models\Subject;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists a subject\'s entries, including ones reached through a photograph', function () {
    $subject = Subject::factory()->person()->create(['slug' => 'clare']);

    $direct = Activity::factory()->create(['occurred_at' => '2026-03-01 09:00:00']);
    $direct->subjects()->attach($subject);

    $viaPhoto = Activity::factory()->create(['occurred_at' => '2026-03-02 09:00:00']);
    $attachment = $viaPhoto->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $attachment->subjects()->attach($subject, ['role' => 'subject', 'x' => 20, 'y' => 30]);

    get('/life/people/clare')->assertOk()
        ->assertInertia(fn ($page) => $page->has('groups', 2));
});

it('hides an unpublished article from a guest', function () {
    $subject = Subject::factory()->person()->create(['slug' => 'clare']);
    Article::factory()->create(['published' => false])->subjects()->attach($subject);

    get('/life/people/clare')->assertInertia(fn ($page) => $page->has('groups', 0));
});

it('previews an unpublished, subject-tagged article for a signed-in request', function () {
    $subject = Subject::factory()->person()->create(['slug' => 'clare']);
    Article::factory()->create(['published' => false])->subjects()->attach($subject);

    get('/life/people/clare')->assertInertia(fn ($page) => $page->has('groups', 0));

    actingAs(User::factory()->create())
        ->get('/life/people/clare')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('groups', 1));
});

it('404s on an unknown kind and an unknown slug', function () {
    get('/life/aliens')->assertNotFound();
    get('/life/people/nobody')->assertNotFound();
});

it('does not let a person\'s slug resolve under the wrong kind', function () {
    Subject::factory()->person()->create(['slug' => 'bella']);

    get('/life/pets/bella')->assertNotFound();
});
