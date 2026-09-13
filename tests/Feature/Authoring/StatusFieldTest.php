<?php

use App\Actions\Entries\UpdateEntryStatus;
use App\Enums\EntryStatus;
use App\Models\Activity;
use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('saves an article as unlisted, keeping its spine row with that status', function () {
    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", ['status' => 'unlisted'])->assertRedirect();

    expect($article->fresh()->status)->toBe(EntryStatus::Unlisted)
        ->and($article->fresh()->timelineEntry->status)->toBe(EntryStatus::Unlisted);
});

it('refuses private without a password, and hashes one when given', function () {
    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", ['status' => 'private'])->assertSessionHasErrors('status');

    $this->patch("/entries/article/{$article->id}", ['status' => 'private', 'password' => 'hunter2'])->assertSessionHasNoErrors();

    expect(Hash::check('hunter2', $article->fresh()->getRawOriginal('password')))->toBeTrue();
});

it('keeps a stored password when the form sends a blank one', function () {
    $article = Article::factory()->create(['status' => EntryStatus::Private, 'password' => 'hunter2']);

    $this->patch("/entries/article/{$article->id}", ['title' => 'Renamed', 'password' => ''])->assertSessionHasNoErrors();

    expect(Hash::check('hunter2', $article->fresh()->getRawOriginal('password')))->toBeTrue();
});

it('refuses a status outside the enum', function () {
    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", ['status' => 'nonsense'])->assertSessionHasErrors('status');
});

it('never drafts a synced entry', function () {
    expect(fn () => app(UpdateEntryStatus::class)(Activity::factory()->create(), EntryStatus::Draft))
        ->toThrow(ValidationException::class);
});
