<?php

use App\Enums\EntryStatus;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Food;
use App\Models\Note;
use App\Models\Scopes\ListedScope;
use App\Models\TimelineEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('sends a guest to log in', function () {
    $activity = Activity::factory()->create();

    $this->patch("/entries/activity/{$activity->id}/status", ['status' => 'unlisted'])->assertRedirect(route('login'));
});

it('lets the owner unlist a synced entry, which takes it off the listings', function () {
    $activity = Activity::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch("/entries/activity/{$activity->id}/status", ['status' => 'unlisted'])
        ->assertSessionHasNoErrors();

    expect($activity->fresh()->status)->toBe(EntryStatus::Unlisted)
        ->and(TimelineEntry::query()->count())->toBe(0);
});

it('refuses a draft for a synced entry and private without a password', function () {
    $activity = Activity::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->patch("/entries/activity/{$activity->id}/status", ['status' => 'draft'])->assertSessionHasErrors('status');
    $this->patch("/entries/activity/{$activity->id}/status", ['status' => 'private'])->assertSessionHasErrors('status');

    $this->patch("/entries/activity/{$activity->id}/status", ['status' => 'private', 'password' => 'hunter2'])->assertSessionHasNoErrors();

    expect($activity->fresh()->password)->toBe('hunter2');
});

it('refuses to publish a draft book missing its author and cover through the status endpoint', function () {
    $book = Book::factory()->create(['title' => 'Atomic Habits', 'status' => 'draft', 'meta' => []]);

    $this->actingAs(User::factory()->create())
        ->patch("/entries/book/{$book->id}/status", ['status' => 'published'])
        ->assertSessionHasErrors(['status' => 'Add an author and a cover before publishing.']);

    expect($book->fresh()->status)->toBe(EntryStatus::Draft);
});

it('applies a food status to every row of the day and to its spine row', function () {
    $first = Food::factory()->create(['occurred_at' => '2026-06-01 08:00:00']);
    $second = Food::factory()->create(['occurred_at' => '2026-06-01 13:00:00']);
    $otherDay = Food::factory()->create(['occurred_at' => '2026-06-02 08:00:00']);

    $this->actingAs(User::factory()->create())
        ->patch("/entries/food/{$first->id}/status", ['status' => 'unlisted'])
        ->assertSessionHasNoErrors();

    expect($second->fresh()->status)->toBe(EntryStatus::Unlisted)
        ->and($otherDay->fresh()->status)->toBe(EntryStatus::Published)
        ->and(TimelineEntry::withoutGlobalScope(ListedScope::class)->where('entry_id', $first->id)->value('status'))->toBe(EntryStatus::Unlisted);
});

it('opens a synced entry in the editor with only its status, and never for a guest', function () {
    $activity = Activity::factory()->create(['occurred_at' => '2026-06-15 07:00:00']);
    $url = $activity->fresh()->url();

    $this->get("{$url}?edit")->assertInertia(fn (Assert $page) => $page->where('editing', false)->where('fields', []));

    $this->actingAs(User::factory()->create())->get("{$url}?edit")->assertInertia(fn (Assert $page) => $page
        ->where('editing', true)
        ->where('editAction', "/entries/activity/{$activity->id}/status")
        ->where('entryId', null)
        ->where('fields', fn ($fields) => collect($fields)->pluck('name')->all() === ['status', 'password'])
        ->where('fields.0.options', fn ($options) => ! collect($options)->pluck('value')->contains('draft')));
});

it('gives a hand-written entry\'s editor its id, only while editing', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-06-20 09:00:00']);
    $url = $note->fresh()->url();
    $this->actingAs(User::factory()->create());

    $this->get("{$url}?edit")->assertInertia(fn (Assert $page) => $page->where('entryId', $note->id));
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('entryId', null));
});

it('drafting a published entry drops its timeline row, and republishing brings it back at the same address', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-06-20 09:00:00', 'status' => 'published']);
    $occurredAt = $note->occurred_at;
    $originalUrl = $note->url();
    $originalSlug = TimelineEntry::query()->where('entry_id', $note->id)->value('url_slug');

    $this->actingAs(User::factory()->create())
        ->patch("/entries/note/{$note->id}/status", ['status' => 'draft'])
        ->assertSessionHasNoErrors();

    $note->refresh();

    expect($note->status)->toBe(EntryStatus::Draft)
        ->and($note->occurred_at->equalTo($occurredAt))->toBeTrue()
        ->and(TimelineEntry::query()->where('entry_id', $note->id)->exists())->toBeFalse();

    $this->patch("/entries/note/{$note->id}/status", ['status' => 'published'])
        ->assertSessionHasNoErrors();

    $note->refresh();

    expect($note->status)->toBe(EntryStatus::Published)
        ->and($note->url())->toBe($originalUrl)
        ->and(TimelineEntry::query()->where('entry_id', $note->id)->value('url_slug'))->toBe($originalSlug);
});
