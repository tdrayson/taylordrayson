<?php

use App\Enums\MediaType;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Calorie;
use App\Models\Concerns\Timelineable;
use App\Models\Media;
use App\Models\Note;
use App\Models\Sleep;

use function Pest\Laravel\get;

function entryUrl(Timelineable $model): string
{
    return $model->occurred_at->format('Y/m/d').'/'.$model->slug();
}

it('renders an activity entry via Inertia', function () {
    $activity = Activity::factory()->create([
        'name' => 'Morning Run',
        'type' => 'run',
        'distance' => 5420,
        'duration' => 2340,
        'occurred_at' => '2026-03-15 07:30:00',
    ]);

    get('/'.entryUrl($activity))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Entry')
            ->where('type', 'activity')
            ->where('accent', 'activity')
            ->where('title', 'Morning Run')
            ->where('entry.distance', fn ($value) => (int) $value === 5420)
        );
});

it('renders a note entry via Inertia', function () {
    $note = Note::factory()->create([
        'content' => 'Finished the migration and went for a run.',
        'occurred_at' => '2026-03-15 20:00:00',
    ]);

    get('/'.entryUrl($note))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Entry')
            ->where('type', 'note')
            ->where('title', null)
            ->where('og.title', fn ($title) => str_contains((string) $title, 'Finished the migration')));
});

it('exposes the polyline when present', function () {
    $activity = Activity::factory()->create([
        'name' => 'Mapped Run',
        'type' => 'run',
        'occurred_at' => '2026-03-15 07:30:00',
        'meta' => ['polyline' => 'abc123', 'elevation_gain' => 40],
    ]);

    get('/'.entryUrl($activity))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Entry')->where('polyline', 'abc123'));
});

it('cites the source with a link back to the platform', function () {
    $activity = Activity::factory()->create([
        'name' => 'Strava Run',
        'type' => 'run',
        'source' => 'strava',
        'source_id' => '12345',
        'occurred_at' => '2026-03-15 07:30:00',
    ]);

    get('/'.entryUrl($activity))->assertInertia(fn ($page) => $page
        ->where('source.platform', 'strava')
        ->where('source.url', 'https://www.strava.com/activities/12345')
    );
});

it('cites a source without a link when the platform has no url', function () {
    $sleep = Sleep::factory()->create(['source' => 'oura', 'occurred_at' => '2026-03-15 06:30:00']);

    get('/'.entryUrl($sleep))->assertInertia(fn ($page) => $page
        ->where('source.platform', 'oura')
        ->where('source.url', null)
    );
});

it('aggregates the whole day for a food entry', function () {
    Calorie::factory()->create(['meal' => 'breakfast', 'calories' => 320, 'occurred_at' => '2026-03-15 08:00:00']);
    $lunch = Calorie::factory()->create(['meal' => 'lunch', 'calories' => 450, 'occurred_at' => '2026-03-15 13:00:00']);

    get('/'.entryUrl($lunch))->assertInertia(fn ($page) => $page
        ->component('Entry')
        ->where('type', 'calorie')
        ->where('entry.totals.calories', 770)
        ->has('entry.meals', 2)
    );
});

it('exposes tags as linkable {name, slug} objects on an article entry', function () {
    $article = Article::factory()->create(['published' => true, 'occurred_at' => '2026-03-15 09:00:00']);
    $article->syncTagNames(['Laravel', 'PHP']);

    get('/'.entryUrl($article))->assertInertia(fn ($page) => $page
        ->component('Entry')
        ->where('entry.tags', fn ($tags) => collect($tags)->sortBy('name')->values()->all() === [
            ['name' => 'Laravel', 'slug' => 'laravel'],
            ['name' => 'PHP', 'slug' => 'php'],
        ])
    );
});

it('returns 404 for an unknown entry slug', function () {
    Activity::factory()->create([
        'name' => 'Morning Run',
        'occurred_at' => '2026-03-15 07:30:00',
    ]);

    get('/2026/03/15/does-not-exist')->assertNotFound();
});

it('renders a media entry via Inertia', function () {
    $media = Media::factory()->create([
        'type' => MediaType::TvEpisode,
        'title' => 'Episode 1',
        'occurred_at' => '2026-03-15 21:00:00',
        'meta' => ['show_title' => 'Jet Lag: The Game', 'season' => 19, 'episode' => 1],
    ]);

    get('/'.entryUrl($media))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Entry')
            ->where('type', 'media')
            ->where('title', 'Episode 1')
            ->where('entry.meta.show_title', 'Jet Lag: The Game')
        );
});
