<?php

use App\Enums\SubjectCategory;
use App\Models\Activity;
use App\Models\Subject;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

it('creates a subject with just a name and a kind', function () {
    actingAs(User::factory()->create())->post('/subjects', ['kind' => 'person', 'name' => 'Clare'])
        ->assertRedirect();

    expect(Subject::query()->where('slug', 'clare')->exists())->toBeTrue();
});

it('stores a category nothing enumerates, normalised', function () {
    actingAs(User::factory()->create())
        ->post('/subjects', ['kind' => 'spot', 'name' => 'The Harrow', 'category' => 'Village Pub'])
        ->assertRedirect();

    expect(Subject::query()->where('slug', 'the-harrow')->value('category'))->toBe('village-pub');
});

it('rejects a second subject whose name slugs to an existing one in the same kind', function () {
    Subject::factory()->person()->create(['slug' => 'clare']);
    $user = actingAs(User::factory()->create());

    $user->post('/subjects', ['kind' => 'person', 'name' => 'Clare'])->assertSessionHasErrors('slug');

    expect(Subject::query()->where('kind', 'person')->where('slug', 'clare')->count())->toBe(1);
});

it('allows the same name across two different kinds', function () {
    Subject::factory()->person()->create(['slug' => 'bella']);

    actingAs(User::factory()->create())->post('/subjects', ['kind' => 'pet', 'name' => 'Bella'])
        ->assertRedirect();

    expect(Subject::query()->where('kind', 'pet')->where('slug', 'bella')->exists())->toBeTrue();
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

it('updates a subject\'s name, bio, category and facts', function () {
    $subject = Subject::factory()->thing()->create();

    actingAs(User::factory()->create())->patch("/subjects/{$subject->id}", [
        'name' => 'Old Nikon',
        'bio' => [['type' => 'paragraph', 'children' => [['text' => 'Bought secondhand.']]]],
        'category' => 'camera',
        'meta' => [['label' => 'Bought', 'value' => '2020']],
    ])->assertRedirect();

    $subject->refresh();

    expect($subject->name)->toBe('Old Nikon')
        ->and($subject->bio)->toBe([['type' => 'paragraph', 'children' => [['text' => 'Bought secondhand.']]]])
        ->and($subject->category)->toBe(SubjectCategory::Camera->value)
        ->and($subject->meta->toArray())->toBe([['label' => 'Bought', 'value' => '2020']]);
});

it('drops a fact row missing either half on save', function () {
    $subject = Subject::factory()->thing()->create();

    actingAs(User::factory()->create())->patch("/subjects/{$subject->id}", [
        'name' => $subject->name,
        'meta' => [
            ['label' => 'Bought', 'value' => '2020'],
            ['label' => 'Incomplete', 'value' => ''],
        ],
    ])->assertRedirect();

    expect($subject->fresh()->meta->toArray())->toBe([['label' => 'Bought', 'value' => '2020']]);
});

it('lists every subject page in the sitemap', function () {
    Subject::factory()->person()->create(['slug' => 'clare']);

    get('/sitemap/pages.xml')->assertOk()
        ->assertSee('/life/people/clare')
        ->assertSee('/life')
        ->assertSee('/life/people');
});

it('refuses a write from a guest', function () {
    post('/subjects', ['kind' => 'person', 'name' => 'Clare'])->assertRedirect('/login');
});

it('refuses an update from a guest', function () {
    $subject = Subject::factory()->person()->create(['name' => 'Clare']);

    patch("/subjects/{$subject->id}", ['name' => 'Someone else'])->assertRedirect('/login');

    expect($subject->fresh()->name)->toBe('Clare');
});
