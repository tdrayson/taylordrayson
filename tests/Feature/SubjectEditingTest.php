<?php

use App\Models\Activity;
use App\Models\Subject;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('creates a subject with just a name and a kind', function () {
    actingAs(User::factory()->create())->post('/subjects', ['kind' => 'person', 'name' => 'Clare'])
        ->assertRedirect();

    expect(Subject::query()->where('slug', 'clare')->exists())->toBeTrue();
});

it('rejects a category belonging to another kind', function () {
    actingAs(User::factory()->create())->post('/subjects', ['kind' => 'person', 'name' => 'Clare', 'category' => 'car'])
        ->assertSessionHasErrors('category');
});

it('removes a subject\'s links without touching the entries or photographs', function () {
    $subject = Subject::factory()->person()->create();
    $activity = Activity::factory()->create();
    $activity->subjects()->attach($subject);
    $attachment = $activity->addMediaFromString(fakeJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $attachment->subjects()->attach($subject, ['role' => 'subject', 'x' => 10, 'y' => 10]);

    actingAs(User::factory()->create())->delete("/subjects/{$subject->id}")->assertRedirect();

    expect($activity->fresh()->exists)->toBeTrue()
        ->and($attachment->fresh()->exists)->toBeTrue()
        ->and($activity->fresh()->allSubjects())->toHaveCount(0);
});

it('lists every subject page in the sitemap', function () {
    Subject::factory()->person()->create(['slug' => 'clare']);

    get('/sitemap/pages.xml')->assertOk()->assertSee('/life/people/clare');
});

it('refuses a write from a guest', function () {
    post('/subjects', ['kind' => 'person', 'name' => 'Clare'])->assertRedirect('/login');
});
