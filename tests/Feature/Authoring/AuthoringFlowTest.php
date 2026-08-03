<?php

use App\Models\Article;
use App\Models\Fuel;
use App\Models\Note;
use App\Models\Page;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('offers a tile for every hand-authored type on /new', function () {
    $this->get('/new')->assertOk()->assertInertia(fn ($page) => $page
        ->component('New')
        ->where('types', fn ($types) => collect($types)->pluck('type')->all() === [
            'note', 'article', 'page', 'project', 'event', 'book', 'fuel', 'appearance',
        ]));
});

it('sends the field definitions for a chosen type', function () {
    $this->get('/new/article')->assertOk()->assertInertia(fn ($page) => $page
        ->where('type', 'article')
        ->where('fields', fn ($fields) => collect($fields)->pluck('name')->contains('content')));
});

it('404s a type nobody authors', function () {
    $this->get('/new/sleep')->assertNotFound();
});

it('creates an entry and lands on it in edit mode', function () {
    $response = $this->post('/entries/note', ['content' => 'A thought.']);

    $response->assertRedirect();

    // Lands on the new entry with the editor already open, so creating and
    // continuing to write are one motion.
    expect($response->headers->get('Location'))->toEndWith('?edit')
        ->and(Note::count())->toBe(1);
});

it('validates against the field definitions', function () {
    // An article needs a title: it is primary, so it is required on create.
    $this->post('/entries/article', [])->assertSessionHasErrors('title');

    // And a select only takes one of its declared options.
    $this->post('/entries/project', ['title' => 'A project', 'status' => 'nonsense'])
        ->assertSessionHasErrors('status');
});

it('saves an edit for any type through one endpoint', function () {
    $article = Article::factory()->create(['title' => 'Before']);

    $this->patch("/entries/article/{$article->id}", ['title' => 'After'])->assertRedirect();

    expect($article->fresh()->title)->toBe('After');
});

it('expands a dotted field name into the nested value it addresses', function () {
    $fuel = Fuel::factory()->create(['litres' => 10]);

    $this->patch("/entries/fuel/{$fuel->id}", ['litres' => 20])->assertRedirect();

    expect($fuel->fresh()->litres)->toEqual(20);
});

it('lists drafts grouped by type, newest edited first', function () {
    Article::factory()->create(['title' => 'Draft article', 'published' => false]);
    Article::factory()->create(['title' => 'Published', 'published' => true]);
    Page::factory()->create(['title' => 'Draft page', 'published' => false]);

    $this->get('/drafts')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Drafts')
        ->where('groups', function ($groups) {
            $labels = collect($groups)->pluck('label')->all();
            $articles = collect($groups)->firstWhere('label', 'Article');

            return $labels === ['Article', 'Page']
                && count($articles['rows']) === 1
                && $articles['rows'][0]['title'] === 'Draft article';
        }));
});

it('keeps the whole authoring surface behind the login', function () {
    auth()->logout();

    $this->get('/new')->assertRedirect('/login');
    $this->get('/drafts')->assertRedirect('/login');
    $this->post('/entries/note', ['content' => 'nope'])->assertRedirect('/login');

    expect(Note::count())->toBe(0);
});
