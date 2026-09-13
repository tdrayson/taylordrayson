<?php

use App\Enums\EntryStatus;
use App\Enums\FieldType;
use App\Fields\AuthorableTypes;
use App\Fields\FieldRegistry;
use App\Models\Note;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('offers a status field on every authorable type', function () {
    foreach (AuthorableTypes::forPicker() as ['type' => $type]) {
        $model = AuthorableTypes::get($type)['model'];

        expect(collect(FieldRegistry::for(new $model))->firstWhere('name', 'status')?->type)->toBe(FieldType::Status, $type);
    }
});

it('saves a hand-written type as a draft, off the timeline and on /drafts', function (string $type, array $payload) {
    $this->actingAs(User::factory()->create());

    $this->post("/entries/{$type}", [...$payload, 'status' => 'draft'])->assertSessionHasNoErrors();

    $model = AuthorableTypes::get($type)['model']::query()->latest('id')->firstOrFail();

    expect($model->status)->toBe(EntryStatus::Draft)
        ->and($model->timelineEntry()->exists())->toBeFalse();

    $this->get('/drafts')->assertInertia(fn (Assert $page) => $page
        ->where('groups', fn ($groups) => collect($groups)->pluck('type')->contains($type)));
})->with([
    'note' => ['note', ['content' => 'Half a thought.', 'slug' => 'half-a-thought']],
    'event' => ['event', ['name' => 'Gig', 'tags' => ['Gig']]],
    'project' => ['project', ['title' => 'A project', 'description' => 'A summary', 'stage' => 'active']],
]);

it('lets the owner open a dated draft at its URL, and 404s it for a guest', function () {
    Note::factory()->create(['slug' => 'secret-plan', 'occurred_at' => '2026-06-15 09:00:00', 'status' => EntryStatus::Draft]);

    $this->get('/2026/06/15/secret-plan')->assertNotFound();

    $this->actingAs(User::factory()->create())->get('/2026/06/15/secret-plan')->assertOk();
});
