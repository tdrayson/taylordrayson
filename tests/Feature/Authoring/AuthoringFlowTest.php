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
            'note', 'article', 'page', 'project', 'event', 'book', 'flight', 'fuel', 'appearance',
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

it('creates an entry and lands on the finished entry', function () {
    $response = $this->post('/entries/note', ['content' => 'A thought.', 'slug' => 'a-thought']);

    $response->assertRedirect();

    expect($response->headers->get('Location'))->not->toEndWith('?edit')
        ->and(Note::count())->toBe(1);
});

it('keeps a draft in the editor, having no entry to show yet', function () {
    $article = Article::factory()->create(['published' => false]);

    $this->patch("/entries/article/{$article->id}", ['title' => 'Still drafting'])
        ->assertRedirect($article->fresh()->url().'?edit');
});

it('validates against the field definitions', function () {
    // An article needs a title: it is primary, so it is required on create.
    $this->post('/entries/article', [])->assertSessionHasErrors('title');

    // And a select only takes one of its declared options.
    $this->post('/entries/project', ['title' => 'A project', 'status' => 'nonsense'])
        ->assertSessionHasErrors('status');

    // A tag is a name. Posting the {name, slug} shape the entry payload uses
    // for its links is a validation failure, not a TypeError inside the sync.
    $this->post('/entries/article', [
        'title' => 'A piece',
        'tags' => [['name' => 'Fitness', 'slug' => 'fitness']],
    ])->assertSessionHasErrors('tags.0');
});

it('saves an edit for any type through one endpoint', function () {
    $article = Article::factory()->create(['title' => 'Before']);

    $this->patch("/entries/article/{$article->id}", ['title' => 'After'])->assertRedirect();

    expect($article->fresh()->title)->toBe('After');
});

it('expands a dotted field name into the nested value it addresses', function () {
    $fuel = Fuel::factory()->create(['odometer' => 10]);

    $this->patch("/entries/fuel/{$fuel->id}", ['odometer' => 20])->assertRedirect();

    expect($fuel->fresh()->odometer)->toEqual(20);
});

it('ignores a computed figure posted at the save endpoint', function () {
    $fuel = Fuel::factory()->create(['litres' => 10, 'cost' => 15.99, 'price_per_litre' => 1.599]);

    $this->patch("/entries/fuel/{$fuel->id}", ['litres' => 999])->assertRedirect();

    expect($fuel->fresh()->litres)->toEqual(10);
});

it('publishes and unpublishes through the same save endpoint', function () {
    // The editor's Publish button sends the flag with the rest of the form, so
    // it has to survive validation built from the field definitions.
    $article = Article::factory()->create(['published' => false]);

    $this->patch("/entries/article/{$article->id}", ['published' => true]);

    expect($article->fresh()->published)->toBeTrue()
        ->and($article->fresh()->timelineEntry()->exists())->toBeTrue();

    $this->patch("/entries/article/{$article->id}", ['published' => false]);

    expect($article->fresh()->published)->toBeFalse()
        ->and($article->fresh()->timelineEntry()->exists())->toBeFalse();
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

it('rejects a flight reason that is not one of the enum cases', function () {
    // Before the enum this was a free-text column: an unknown value saved fine
    // and then threw a ValueError the next time the row was read.
    $this->postJson('/entries/flight', [
        'occurred_at' => '2026-08-04 09:00:00',
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
        'reason' => 'commuting',
    ])->assertJsonValidationErrors('reason');
});

it('keeps the field order the fields class declares', function () {
    $names = collect($this->get('/new/flight')->viewData('page')['props']['fields'])->pluck('name');

    expect($names->take(3)->all())->toBe(['occurred_at', 'departure_timezone', 'origin_iata']);
});

it('sends a new fuel entry to the finished entry, not back to a form', function () {
    $response = $this->post('/entries/fuel', [
        'occurred_at' => '2026-08-13 12:00:00',
        'cost' => 51.87,
        'price_per_litre' => 1.599,
        'station_name' => 'Beddington Lane Service Station',
    ]);

    $fuel = Fuel::latest('id')->sole();

    $response->assertRedirect($fuel->url());

    $this->get($fuel->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('editing', false)
            ->where('entry.id', $fuel->id)
            ->where('entry.litres', 32.439));
});
