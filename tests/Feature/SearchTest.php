<?php

use App\Enums\ResponseKind;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Checkin;
use App\Models\Citation;
use App\Models\Event;
use App\Models\Note;
use App\Models\Page;
use App\Models\Place;
use App\Models\User;
use App\Search\SearchSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

it('returns matching entries with a navigable url', function () {
    Activity::factory()->create(['name' => 'Parkrun at Lloyd Park', 'type' => 'run', 'occurred_at' => '2026-03-15 08:00:00']);
    Activity::factory()->create(['name' => 'Evening Walk', 'type' => 'walk', 'occurred_at' => '2026-03-16 18:00:00']);

    getJson('/search/suggest?q=parkrun')->assertOk()->assertJson(fn ($json) => $json
        ->has('results', 1)
        ->where('results.0.title', 'Parkrun at Lloyd Park')
        ->where('results.0.type', 'activity')
        ->where('results.0.url', fn ($url) => str_contains($url, '/2026/03/15/'))
        ->etc()
    );
});

it('searches across multiple types and orders by recency', function () {
    Place::factory()->create(['venue_name' => 'Coffee Lab', 'occurred_at' => '2026-01-10 09:00:00']);
    Note::factory()->create(['content' => 'Thinking about coffee roasting', 'occurred_at' => '2026-05-01 09:00:00']);

    getJson('/search/suggest?q=coffee')->assertOk()->assertJson(fn ($json) => $json
        ->has('results', 2)
        ->where('results.0.type', 'note')
        ->where('results.1.type', 'place')
        ->etc()
    );
});

it('does not add a query per reply note to fetch the citation the card reads', function () {
    foreach (range(0, 4) as $i) {
        $citation = Citation::factory()->create(['url' => "https://example.com/cited-{$i}"]);

        Note::factory()->create([
            'content' => "Distinctivewebmention note {$i}",
            'response_kind' => ResponseKind::Reply,
            'response_url' => $citation->url,
        ]);
    }

    DB::enableQueryLog();
    getJson('/search/suggest?q=distinctivewebmention')->assertOk()->assertJson(fn ($json) => $json->has('results', 5)->etc());
    $citationQueries = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'citations'))
        ->count();
    DB::disableQueryLog();

    // One query for all five citations, eager loaded with the notes: not one
    // per reply, which is what a lazy-loaded relation would cost here.
    expect($citationQueries)->toBe(1);
});

it('ignores queries shorter than two characters', function () {
    Activity::factory()->create(['name' => 'Run', 'type' => 'run', 'occurred_at' => now()]);

    getJson('/search/suggest?q=r')->assertOk()->assertExactJson(['results' => [], 'destinations' => []]);
});

it('suggests taxonomy destination pages drawn live from the registry', function () {
    Activity::factory()->create(['name' => 'Morning miles', 'type' => 'run', 'occurred_at' => '2026-03-15 08:00:00']);
    Activity::factory()->create(['name' => 'Evening stroll', 'type' => 'walk', 'occurred_at' => '2026-03-16 18:00:00']);

    $destinations = getJson('/search/suggest?q=run')->assertOk()->json('destinations');

    // The section is the taxonomy's kind ("Type"), not the owning type's label
    // ("Activities"), so a run reads as an activity type, not an entry.
    expect(collect($destinations)->firstWhere('url', '/activities/run'))
        ->toMatchArray(['label' => 'Run', 'section' => 'Type', 'type' => 'activity', 'tag' => false]);

    expect(collect($destinations)->pluck('url'))->not->toContain('/activities/walk');
});

it('labels tag destinations as tags, not the owning type', function () {
    $article = Article::factory()->create(['status' => 'published', 'occurred_at' => now()]);
    $article->syncTagNames(['Fluent Forms']);

    $destinations = getJson('/search/suggest?q=fluent')->assertOk()->json('destinations');

    expect(collect($destinations)->firstWhere('url', '/tags/fluent-forms'))
        ->toMatchArray(['label' => 'Fluent Forms', 'section' => 'Tag', 'tag' => true]);
});

it('suggests standalone pages as destinations', function () {
    Page::factory()->create(['status' => 'published', 'title' => 'Sleep score', 'slug' => 'sleep-score']);

    $destinations = getJson('/search/suggest?q=Sleep score')->assertOk()->json('destinations');

    expect(collect($destinations)->firstWhere('url', '/sleep-score'))
        ->toMatchArray(['label' => 'Sleep score', 'section' => 'Page', 'type' => 'page', 'tag' => false]);
});

it('hides an unlisted page from guests, and shows it to the owner', function () {
    Page::factory()->create(['status' => 'unlisted', 'title' => 'Draft colophon', 'slug' => 'draft-colophon']);

    $urls = fn (array $json): array => collect($json)->pluck('url')->all();

    expect($urls(getJson('/search/suggest?q=Draft colophon')->assertOk()->json('destinations')))
        ->not->toContain('/draft-colophon');

    $seen = $this->actingAs(User::factory()->create())
        ->getJson('/search/suggest?q=Draft colophon')
        ->assertOk()
        ->json('destinations');

    expect($urls($seen))->toContain('/draft-colophon');
});

it('hides unpublished articles from guest search suggestions', function () {
    Article::factory()->create(['status' => 'draft', 'title' => 'Secret draft thoughts', 'occurred_at' => now()]);

    getJson('/search/suggest?q=Secret draft')
        ->assertOk()
        ->assertJsonMissing(['title' => 'Secret draft thoughts']);
});

it('shows unlisted articles in suggestions to the owner, but never drafts', function () {
    Article::factory()->create(['status' => 'unlisted', 'title' => 'Secret unlisted thoughts', 'occurred_at' => now()]);
    Article::factory()->create(['status' => 'draft', 'title' => 'Secret draft thoughts', 'occurred_at' => now()]);

    $this->actingAs(User::factory()->create())
        ->getJson('/search/suggest?q=Secret')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Secret unlisted thoughts'])
        ->assertJsonMissing(['title' => 'Secret draft thoughts']);
});

it('shows published articles in suggestions to guests', function () {
    Article::factory()->create(['status' => 'published', 'title' => 'Public announcement post', 'occurred_at' => now()]);

    getJson('/search/suggest?q=Public announcement')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Public announcement post']);
});

it('filters events by the renamed description column via the advanced search builder', function () {
    Event::factory()->create(['name' => 'Arctic Monkeys', 'description' => 'General admission standing', 'occurred_at' => now()]);
    Event::factory()->create(['name' => 'Hamilton', 'description' => 'Balcony seats', 'occurred_at' => now()]);

    $filter = [[
        'type' => 'event',
        'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'standing']],
    ]];

    // Guards against the SQL error that followed the notes -> description rename:
    // the schema previously still pointed the "notes" field at a dropped column.
    get('/search?'.http_build_query(['filter' => json_encode($filter)]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('total', 1));
});

it('filters events by the new organiser column via the advanced search builder', function () {
    Event::factory()->create(['name' => 'Conference talk', 'organiser' => 'Acme Corp', 'occurred_at' => now()]);
    Event::factory()->create(['name' => 'Gig', 'organiser' => 'Other Co', 'occurred_at' => now()]);

    $filter = [[
        'type' => 'event',
        'conditions' => [['field' => 'organiser', 'operator' => 'contains', 'value' => 'Acme']],
    ]];

    get('/search?'.http_build_query(['filter' => json_encode($filter)]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('total', 1));
});

it('only references columns that actually exist on the events table in the search schema', function () {
    $eventFields = collect(SearchSchema::types()['event']['fields']);

    $columns = $eventFields
        ->pluck('column')
        ->filter()
        ->unique();

    expect($columns)->not->toBeEmpty();

    $columns->each(fn (string $column) => expect(Schema::hasColumn('events', $column))
        ->toBeTrue("Expected events table to have column [{$column}] referenced by SearchSchema."));
});
