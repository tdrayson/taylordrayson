<?php

use App\Models\Article;
use App\Models\Event;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('lists visible tags with per-tag usage counts', function () {
    $first = Event::factory()->create(['type' => 'theatre', 'occurred_at' => now()->subDay()]);
    $second = Event::factory()->create(['type' => 'theatre', 'occurred_at' => now()->subDays(2)]);
    $note = Note::factory()->create(['occurred_at' => now()->subDays(3)]);

    $first->syncTagNames(['Theatre']);
    $second->syncTagNames(['Theatre']);
    $note->syncTagNames(['Coffee']);

    get('/tags')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Tags')
        ->where('tags', function ($tags): bool {
            $theatre = collect($tags)->firstWhere('slug', 'theatre');

            return $theatre['count'] === 2
                && collect($tags)->pluck('slug')->contains('coffee');
        })
    );
});

it('hides a tag that lives only on an unpublished article from guests, but shows it to the owner', function () {
    $draft = Article::factory()->create(['published' => false, 'occurred_at' => now()]);
    $draft->syncTagNames(['Secret Launch']);

    get('/tags')->assertInertia(fn (Assert $page) => $page
        ->where('tags', fn ($tags) => ! collect($tags)->pluck('slug')->contains('secret-launch')));

    actingAs(User::factory()->create());

    get('/tags')->assertInertia(fn (Assert $page) => $page
        ->where('tags', fn ($tags) => collect($tags)->pluck('slug')->contains('secret-launch')));
});
