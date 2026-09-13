<?php

use App\Actions\Entries\UpdateEntryStatus;
use App\Enums\EntryStatus;
use App\Models\Activity;
use App\Models\Article;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('saves an article as unlisted, keeping its spine row with that status', function () {
    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", ['status' => 'unlisted'])->assertRedirect();

    expect($article->fresh()->status)->toBe(EntryStatus::Unlisted)
        ->and($article->fresh()->timelineEntry->status)->toBe(EntryStatus::Unlisted);
});

it('refuses private without a password, and stores one encrypted when given', function () {
    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", ['status' => 'private'])->assertSessionHasErrors('status');

    $this->patch("/entries/article/{$article->id}", ['status' => 'private', 'password' => 'hunter2'])->assertSessionHasNoErrors();

    expect($article->fresh()->password)->toBe('hunter2')
        ->and($article->fresh()->getRawOriginal('password'))->not->toBe('hunter2');
});

it('keeps a stored password when the form sends a blank one', function () {
    $article = Article::factory()->create(['status' => EntryStatus::Private, 'password' => 'hunter2']);

    $this->patch("/entries/article/{$article->id}", ['title' => 'Renamed', 'password' => ''])->assertSessionHasNoErrors();

    expect($article->fresh()->password)->toBe('hunter2');
});

it('shows the owner the password in the editor, and nobody else', function () {
    $article = Article::factory()->create(['occurred_at' => '2026-06-15 09:00:00', 'status' => EntryStatus::Private, 'password' => 'hunter2']);
    $url = $article->fresh()->url();

    $this->get("{$url}?edit")->assertInertia(fn ($page) => $page->where('password', 'hunter2'));

    auth()->logout();
    $this->post("/unlock/article/{$article->id}", ['password' => 'hunter2']);

    $this->get($url)->assertInertia(fn ($page) => $page->where('locked', false)->where('password', null));
});

it('does not reuse a password left from an earlier private spell', function () {
    $article = Article::factory()->create(['status' => EntryStatus::Unlisted, 'password' => 'hunter2']);

    $this->patch("/entries/article/{$article->id}", ['status' => 'private', 'password' => ''])->assertSessionHasErrors('status');

    expect($article->fresh()->status)->toBe(EntryStatus::Unlisted);
});

it('refuses a status outside the enum', function () {
    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", ['status' => 'nonsense'])->assertSessionHasErrors('status');
});

it('never drafts a synced entry', function () {
    expect(fn () => app(UpdateEntryStatus::class)(Activity::factory()->create(), EntryStatus::Draft))
        ->toThrow(ValidationException::class);
});
