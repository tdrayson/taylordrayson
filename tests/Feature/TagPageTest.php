<?php

use App\Models\Article;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use App\Support\PortableText;

use function Pest\Laravel\get;

it('shows a cross-type feed of every article, note, and project carrying the tag', function () {
    $article = Article::factory()->create(['title' => 'Tagged Article', 'published' => true]);
    $note = Note::factory()->create(['content' => 'Tagged note content']);
    $project = Project::factory()->create(['title' => 'Tagged Project']);

    $article->syncTagNames(['Laravel']);
    $note->syncTagNames(['Laravel']);
    $project->syncTagNames(['Laravel']);

    get('/tags/laravel')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Tag')
        ->where('name', 'Laravel')
        ->where('groups', function ($groups) {
            $titles = collect($groups)->flatMap(fn ($group) => collect($group['items'])->pluck('title'))->all();

            return in_array('Tagged Article', $titles, true)
                && in_array('Tagged Project', $titles, true)
                && collect($groups)
                    ->flatMap(fn ($group) => collect($group['items'])->pluck('body'))
                    ->map(fn ($body) => PortableText::plainText($body))
                    ->contains('Tagged note content');
        })
    );
});

it('returns 404 for an unknown tag slug', function () {
    get('/tags/no-such-tag')->assertNotFound();
});

it('404s for a guest when the tag only exists on an unpublished article, but renders for an authenticated user', function () {
    $article = Article::factory()->create(['published' => false]);
    $article->syncTagNames(['Draft Only']);

    get('/tags/draft-only')->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get('/tags/draft-only')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tag')
            ->where('name', 'Draft Only')
        );
});

it('carries tags as linkable {name, slug, url} objects on an article entry payload', function () {
    $article = Article::factory()->create(['published' => true]);
    $article->syncTagNames(['Laravel']);

    get('/'.$article->occurred_at->format('Y/m/d').'/'.$article->slug())
        ->assertInertia(fn ($page) => $page
            ->where('entry.tags', [['name' => 'Laravel', 'slug' => 'laravel', 'url' => '/tags/laravel']])
        );
});
