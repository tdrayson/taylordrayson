<?php

use App\Models\Activity;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Article;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\User;
use App\Search\SearchPresets;
use App\Support\Distance;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

function searchUrl(array $filter): string
{
    return '/search?'.http_build_query(['filter' => json_encode($filter)]);
}

/** Attach `$count` photos (the first as the cover) to a media-bearing model. */
function attachPhotos(object $model, int $count): void
{
    foreach (range(1, $count) as $index) {
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $model->addMediaFromString($bytes)
            ->usingFileName("p{$index}.jpg")
            ->toMediaCollection($index === 1 ? 'cover' : 'photos');
    }
}

function makeFlight(string $airlineIcao, int $miles, string $occurredAt): void
{
    Flight::factory()->create([
        'airline_icao' => $airlineIcao,
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
        'distance' => Distance::fromMiles($miles),
        'occurred_at' => $occurredAt,
    ]);
}

beforeEach(function () {
    Airport::create(['iata_code' => 'LHR', 'name' => 'Heathrow', 'latitude' => 51.47, 'longitude' => -0.4543]);
    Airport::create(['iata_code' => 'JFK', 'name' => 'JFK', 'latitude' => 40.6413, 'longitude' => -73.7781]);
    Airline::create(['icao_code' => 'EZY', 'iata_code' => 'U2', 'name' => 'easyJet']);
    Airline::create(['icao_code' => 'BAW', 'iata_code' => 'BA', 'name' => 'British Airways']);
});

it('renders the search page via Inertia', function () {
    get('/search')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Search')
        ->where('total', 0)
        ->has('schema')
    );
});

it('ANDs conditions within a group (flights over 300mi with easyJet)', function () {
    makeFlight('EZY', 350, '2026-05-01 09:00:00'); // matches both
    makeFlight('EZY', 200, '2026-05-02 09:00:00'); // fails distance
    makeFlight('BAW', 400, '2026-05-03 09:00:00'); // fails airline

    // The client now converts to storage units before sending; the server does no scaling.
    $url = searchUrl([[
        'type' => 'flight',
        'conditions' => [
            ['field' => 'distance', 'operator' => 'gt', 'value' => Distance::fromMiles(300)],
            ['field' => 'airline', 'operator' => 'contains', 'value' => 'easyJet'],
        ],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});

it('activity distance filter treats the value as stored metres (no server scaling)', function () {
    Activity::factory()->create(['type' => 'run', 'distance' => Distance::fromMiles(5), 'occurred_at' => now()]);

    // The wire contract is raw storage units: no km/mi scaling happens server-side.
    $matches = searchUrl([[
        'type' => 'activity',
        'conditions' => [['field' => 'distance', 'operator' => 'gte', 'value' => Distance::fromMiles(5)]],
    ]]);
    get($matches)->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));

    $tooFar = searchUrl([[
        'type' => 'activity',
        'conditions' => [['field' => 'distance', 'operator' => 'gte', 'value' => Distance::fromMiles(6)]],
    ]]);
    get($tooFar)->assertOk()->assertInertia(fn ($page) => $page->where('total', 0));
});

it('ORs between groups of different types', function () {
    makeFlight('EZY', 350, '2026-05-01 09:00:00');
    Activity::factory()->create(['type' => 'walk', 'occurred_at' => now()->subDays(3)]);
    Activity::factory()->create(['type' => 'run', 'occurred_at' => now()->subDays(3)]);

    $url = searchUrl([
        [
            'type' => 'flight',
            'conditions' => [['field' => 'distance', 'operator' => 'gt', 'value' => Distance::fromMiles(300)]],
        ],
        [
            'type' => 'activity',
            'conditions' => [['field' => 'kind', 'operator' => 'is', 'value' => 'walk']],
        ],
    ]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 2));
});

it('filters a date range inclusive of the end day', function () {
    Activity::factory()->create(['name' => 'On end day', 'type' => 'run', 'occurred_at' => '2026-03-31 22:00:00']);
    Activity::factory()->create(['name' => 'After', 'type' => 'run', 'occurred_at' => '2026-04-01 09:00:00']);

    $url = searchUrl([[
        'type' => 'activity',
        'conditions' => [['field' => 'day', 'operator' => 'between', 'value' => ['2026-03-01', '2026-03-31']]],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});

it('filters by a whole month or year', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => '2026-03-15 08:00:00']);
    Activity::factory()->create(['type' => 'run', 'occurred_at' => '2026-07-15 08:00:00']);
    Activity::factory()->create(['type' => 'run', 'occurred_at' => '2025-03-15 08:00:00']);

    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'month', 'operator' => 'in', 'value' => '2026-03']]]]))
        ->assertInertia(fn ($page) => $page->where('total', 1));

    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'year', 'operator' => 'in', 'value' => '2026']]]]))
        ->assertInertia(fn ($page) => $page->where('total', 2));
});

it('filters between two months and before a year', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => '2026-02-15 08:00:00']); // in Jan–Mar
    Activity::factory()->create(['type' => 'run', 'occurred_at' => '2026-05-15 08:00:00']); // out
    Activity::factory()->create(['type' => 'run', 'occurred_at' => '2022-06-15 08:00:00']); // before 2023

    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'month', 'operator' => 'between', 'value' => ['2026-01', '2026-03']]]]]))
        ->assertInertia(fn ($page) => $page->where('total', 1));

    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'year', 'operator' => 'before', 'value' => '2023']]]]))
        ->assertInertia(fn ($page) => $page->where('total', 1));
});

it('exposes enum options in the client schema', function () {
    Activity::factory()->create(['type' => 'walk', 'occurred_at' => now()]);
    Activity::factory()->create(['type' => 'run', 'occurred_at' => now()]);

    get('/search')->assertInertia(fn ($page) => $page
        ->where('schema', fn ($schema) => collect($schema)
            ->firstWhere('type', 'activity')['fields']
            ? collect(collect($schema)->firstWhere('type', 'activity')['fields'])
                ->firstWhere('key', 'kind')['options'] === ['run', 'walk']
            : false)
    );
});

it('searches an expanded field (activity name contains)', function () {
    Activity::factory()->create(['name' => 'Bicep curls and rows', 'type' => 'gym', 'occurred_at' => now()]);
    Activity::factory()->create(['name' => 'Morning run', 'type' => 'run', 'occurred_at' => now()]);

    $url = searchUrl([[
        'type' => 'activity',
        'conditions' => [['field' => 'name', 'operator' => 'contains', 'value' => 'bicep']],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});

it('filters Anything by a date range across types', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => '2026-03-10 08:00:00']);
    Checkin::factory()->create(['venue_name' => 'Cafe', 'occurred_at' => '2026-03-12 09:00:00']);
    Activity::factory()->create(['type' => 'run', 'occurred_at' => '2026-04-10 08:00:00']);

    $url = searchUrl([[
        'type' => 'any',
        'conditions' => [['field' => 'day', 'operator' => 'between', 'value' => ['2026-03-01', '2026-03-31']]],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 2));
});

it('filters Anything by free text across types', function () {
    Checkin::factory()->create(['venue_name' => 'Zephyr Lounge', 'occurred_at' => now()->subDay()]);
    Activity::factory()->create(['name' => 'Zephyr ride', 'type' => 'cycle', 'occurred_at' => now()->subDays(2)]);
    Activity::factory()->create(['name' => 'Plain run', 'type' => 'run', 'occurred_at' => now()->subDays(3)]);

    $url = searchUrl([[
        'type' => 'any',
        'conditions' => [['field' => 'text', 'operator' => 'contains', 'value' => 'Zephyr']],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 2));
});

it('auto-orders a reversed range', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => '2026-02-15 08:00:00']);
    Activity::factory()->create(['type' => 'run', 'occurred_at' => '2026-09-15 08:00:00']);

    // Reversed: later year first. Should still match the entry in between.
    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'month', 'operator' => 'between', 'value' => ['2026-04', '2026-01']]]]]))
        ->assertInertia(fn ($page) => $page->where('total', 1));
});

it('supports ends with and not between', function () {
    Activity::factory()->create(['name' => 'Evening yoga', 'type' => 'yoga', 'occurred_at' => '2026-05-15 08:00:00']);
    Activity::factory()->create(['name' => 'Morning run', 'type' => 'run', 'occurred_at' => '2026-05-16 08:00:00']);

    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'name', 'operator' => 'ends_with', 'value' => 'yoga']]]]))
        ->assertInertia(fn ($page) => $page->where('total', 1));

    // Not in March–June excludes both May entries, leaving none.
    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'month', 'operator' => 'not_between', 'value' => ['2026-03', '2026-06']]]]]))
        ->assertInertia(fn ($page) => $page->where('total', 0));
});

it('filters a duration (stored in seconds)', function () {
    Activity::factory()->create(['name' => 'Long run', 'type' => 'run', 'duration' => 1800, 'occurred_at' => now()]);
    Activity::factory()->create(['name' => 'Quick jog', 'type' => 'run', 'duration' => 600, 'occurred_at' => now()]);

    // Over 20 minutes = over 1200 seconds (the control sends seconds).
    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'duration', 'operator' => 'gt', 'value' => 1200]]]]))
        ->assertInertia(fn ($page) => $page->where('total', 1));
});

it('exposes unit prefix/suffix in the client schema', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => now()]);

    get('/search')->assertInertia(fn ($page) => $page
        ->where('schema', function ($schema) {
            $activityFields = collect(collect($schema)->firstWhere('type', 'activity')['fields']);
            $fuelFields = collect(collect($schema)->firstWhere('type', 'fuel')['fields']);

            // Distance fields no longer carry a static km/mi suffix: the client
            // converts using the schema's storage unit and the visitor's live
            // mi/km preference instead.
            $distance = $activityFields->firstWhere('key', 'distance');

            return $distance['measure'] === 'distance'
                && $distance['store'] === 'm'
                && $activityFields->firstWhere('key', 'calories')['suffix'] === 'kcal'
                && $fuelFields->firstWhere('key', 'litres')['suffix'] === 'L'
                && $fuelFields->firstWhere('key', 'cost')['prefix'] === '£';
        })
    );
});

it('matches any of several values with enum "is"', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => now()]);
    Activity::factory()->create(['type' => 'walk', 'occurred_at' => now()]);
    Activity::factory()->create(['type' => 'swim', 'occurred_at' => now()]);

    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'kind', 'operator' => 'is', 'value' => ['run', 'walk']]]]]))
        ->assertInertia(fn ($page) => $page->where('total', 2));
});

it('excludes several values with enum "is not"', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => now()]);
    Activity::factory()->create(['type' => 'walk', 'occurred_at' => now()]);
    Activity::factory()->create(['type' => 'swim', 'occurred_at' => now()]);

    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'kind', 'operator' => 'is_not', 'value' => ['run', 'walk']]]]]))
        ->assertInertia(fn ($page) => $page->where('total', 1));
});

it('supports text operators on an enum column', function () {
    Activity::factory()->create(['type' => 'trail_run', 'occurred_at' => now()]);
    Activity::factory()->create(['type' => 'walk', 'occurred_at' => now()]);

    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'kind', 'operator' => 'contains', 'value' => 'run']]]]))
        ->assertInertia(fn ($page) => $page->where('total', 1));
});

it('filters activities by photo count', function () {
    Storage::fake('public');

    attachPhotos(Activity::factory()->create(['type' => 'run', 'name' => 'Big trip', 'occurred_at' => now()]), 3);
    attachPhotos(Activity::factory()->create(['type' => 'run', 'name' => 'One shot', 'occurred_at' => now()]), 1);
    Activity::factory()->create(['type' => 'run', 'name' => 'No photos', 'occurred_at' => now()]);

    // More than 2 photos: only the 3-photo activity.
    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'photos', 'operator' => 'gt', 'value' => 2]]]]))
        ->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));

    // Has at least one photo: the 3-photo and 1-photo activities.
    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'photos', 'operator' => 'gte', 'value' => 1]]]]))
        ->assertInertia(fn ($page) => $page->where('total', 2));
});

it('filters by has any / has none photos', function () {
    Storage::fake('public');

    attachPhotos(Activity::factory()->create(['type' => 'run', 'name' => 'With pics', 'occurred_at' => now()]), 2);
    Activity::factory()->create(['type' => 'run', 'name' => 'Bare', 'occurred_at' => now()]);

    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'photos', 'operator' => 'has_any', 'value' => null]]]]))
        ->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));

    get(searchUrl([['type' => 'activity', 'conditions' => [['field' => 'photos', 'operator' => 'has_none', 'value' => null]]]]))
        ->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});

it('filters Anything that has photos across types', function () {
    Storage::fake('public');

    attachPhotos(Activity::factory()->create(['type' => 'run', 'occurred_at' => now()->subDay()]), 2);
    Checkin::factory()->create(['venue_name' => 'Cafe', 'occurred_at' => now()->subDays(2)]);

    get(searchUrl([['type' => 'any', 'conditions' => [['field' => 'photos', 'operator' => 'gt', 'value' => 0]]]]))
        ->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});

it('exposes ready-made example searches', function () {
    get('/search')->assertOk()->assertInertia(fn ($page) => $page
        ->has('presets', 6)
        ->has('presets.0.label')
        ->has('presets.0.filter')
    );
});

it('runs a preset filter to real results', function () {
    Activity::factory()->create(['type' => 'run', 'distance' => Distance::fromKm(12), 'occurred_at' => now()]); // matches (>= 5km run)
    Activity::factory()->create(['type' => 'run', 'distance' => Distance::fromKm(3), 'occurred_at' => now()]);  // too short
    Activity::factory()->create(['type' => 'walk', 'distance' => Distance::fromKm(15), 'occurred_at' => now()]); // not a run

    $preset = collect(SearchPresets::all())->firstWhere('key', 'long-runs');

    get(searchUrl($preset['filter']))->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});

it('orders results newest or oldest first', function () {
    Activity::factory()->create(['name' => 'Older', 'type' => 'run', 'occurred_at' => '2026-01-01 09:00:00']);
    Activity::factory()->create(['name' => 'Newer', 'type' => 'run', 'occurred_at' => '2026-06-01 09:00:00']);

    $filter = [['type' => 'activity', 'conditions' => [['field' => 'kind', 'operator' => 'is', 'value' => ['run']]]]];

    get(searchUrl($filter))->assertInertia(fn ($page) => $page
        ->where('order', 'newest')
        ->where('groups.0.items.0.title', 'Newer')
    );

    get(searchUrl($filter).'&order=oldest')->assertInertia(fn ($page) => $page
        ->where('order', 'oldest')
        ->where('groups.0.items.0.title', 'Older')
    );
});

it('never surfaces a stale unpublished article to a guest via the advanced search filter', function () {
    $article = Article::factory()->create(['published' => true, 'title' => 'Now hidden post', 'occurred_at' => now()]);

    // A mass update via the query builder bypasses the TimelineEntryObserver,
    // so the timeline_entries row is left behind stale (not deleted) even
    // though the article is now unpublished. guardPublished() is the only
    // thing standing between this stale row and a guest search result.
    Article::query()->where('id', $article->id)->update(['published' => false]);

    $url = searchUrl([[
        'type' => 'article',
        'conditions' => [['field' => 'title', 'operator' => 'contains', 'value' => 'hidden']],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 0));

    $this->actingAs(User::factory()->create());

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});

it('drops unknown fields and disallowed operators', function () {
    makeFlight('EZY', 350, '2026-05-01 09:00:00');

    // `eq` is not valid for a text field, and `bogus` is not a field — both dropped,
    // leaving only the valid distance clause.
    $url = searchUrl([[
        'type' => 'flight',
        'conditions' => [
            ['field' => 'airline', 'operator' => 'eq', 'value' => 'easyJet'],
            ['field' => 'bogus', 'operator' => 'gt', 'value' => 1],
            ['field' => 'distance', 'operator' => 'gt', 'value' => Distance::fromMiles(300)],
        ],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});
