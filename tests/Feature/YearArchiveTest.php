<?php

use App\Enums\MediaType;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Calorie;
use App\Models\Flight;
use App\Models\Media;
use App\Models\Note;
use App\Models\Sleep;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

function yearPhotoJpegBytes(): string
{
    $image = imagecreatetruecolor(640, 480);
    imagefilledrectangle($image, 0, 0, 639, 479, imagecolorallocate($image, 90, 120, 40));
    ob_start();
    imagejpeg($image, null, 80);

    return (string) ob_get_clean();
}

it('serves real year numbers, entry count and heatmap', function () {
    Activity::factory()->create(['occurred_at' => '2025-03-10 09:00:00', 'distance' => 5000]);
    Activity::factory()->create(['occurred_at' => '2025-03-10 18:00:00', 'distance' => 7000]);
    Sleep::factory()->create(['occurred_at' => '2025-03-11 00:00:00', 'duration' => 8 * 3600]);
    Flight::factory()->create(['occurred_at' => '2025-06-01 10:00:00']);
    Article::factory()->create(['occurred_at' => '2025-07-01 12:00:00', 'published' => true]);
    Note::factory()->create(['occurred_at' => '2025-07-02 12:00:00']);

    get('/2025')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Year')
            ->where('entriesCount', 6)
            ->where('stats', fn ($stats) => collect($stats)->pluck('label')->contains('Activities'))
            ->where('stats', fn ($stats) => collect($stats)->pluck('label')->contains('Written'))
            ->where('heatmap.2025-03-10', 2)
            ->where('heatmap.2025-06-01', 1));
});

it('splits distance by discipline, averages daily food, and adds a year-only superlative', function () {
    // Metres chosen to land on clean mile values.
    Activity::factory()->create(['type' => 'walk', 'distance' => 16093, 'occurred_at' => '2025-06-05 08:00:00']); // 10 mi
    Activity::factory()->create(['type' => 'run', 'distance' => 8047, 'occurred_at' => '2025-06-06 08:00:00']);   // 5 mi (longest)
    Activity::factory()->create(['type' => 'run', 'distance' => 4828, 'occurred_at' => '2025-06-07 08:00:00']);   // 3 mi
    Activity::factory()->create(['type' => 'ride', 'distance' => 32187, 'occurred_at' => '2025-06-08 08:00:00']); // 20 mi

    // 6000 kcal across 3 logged days -> 2000 kcal/day.
    Calorie::factory()->create(['calories' => 1000, 'occurred_at' => '2025-06-01 08:00:00']);
    Calorie::factory()->create(['calories' => 1000, 'occurred_at' => '2025-06-01 12:00:00']);
    Calorie::factory()->create(['calories' => 2000, 'occurred_at' => '2025-06-02 12:00:00']);
    Calorie::factory()->create(['calories' => 2000, 'occurred_at' => '2025-06-03 12:00:00']);

    get('/2025')->assertInertia(fn ($page) => $page
        ->component('Year')
        ->where('stats', function ($stats) {
            $by = collect($stats)->keyBy('label');

            // Distance stats now send raw metres (client formats via useFormat); the
            // walk is a single 16093 m activity, "Ran" sums both runs, and the
            // year-only superlative takes the single longest run.
            return $by['Walked']['distanceM'] === 16093
                && $by['Ran']['distanceM'] === 12875
                && $by['Cycled']['distanceM'] === 32187
                && $by['Food']['value'] === '2,000'
                && $by['Food']['unit'] === 'kcal/day'
                && $by['Longest run']['distanceM'] === 8047;
        }));

    // The same month shows the disciplines but omits the year-only superlative.
    get('/2025/06')->assertInertia(fn ($page) => $page
        ->component('Month')
        ->where('stats', fn ($stats) => collect($stats)->pluck('label')->contains('Walked')
            && collect($stats)->pluck('label')->doesntContain('Longest run')));
});

it('counts tv episodes (not just films) in the "Watched" stat', function () {
    // Regression guard for the periodStats bug where `whereIn('type', ['film', 'show'])`
    // used the non-existent value 'show' instead of the real 'episode', silently
    // undercounting TV in the year/month "Watched" stat.
    Media::factory()->create(['type' => MediaType::TvEpisode, 'occurred_at' => '2025-04-01 20:00:00']);
    Media::factory()->create(['type' => MediaType::Film, 'occurred_at' => '2025-04-02 20:00:00']);
    Media::factory()->create(['type' => MediaType::Book, 'occurred_at' => '2025-04-03 20:00:00']);

    get('/2025')->assertInertia(fn ($page) => $page
        ->where('stats', fn ($stats) => collect($stats)->firstWhere('label', 'Watched')['value'] === '2'));
});

it('excludes other years from the aggregates', function () {
    Activity::factory()->create(['occurred_at' => '2024-12-31 09:00:00']);

    get('/2025')->assertInertia(fn ($page) => $page
        ->where('entriesCount', 0)
        ->where('stats', [])
        ->where('heatmap', []));
});

it('serves the year timeline tail ascending, day-paginated and deferred', function () {
    foreach (range(1, 12) as $day) {
        Note::factory()->create(['occurred_at' => sprintf('2025-05-%02d 10:00:00', $day)]);
    }

    // Initial load: deferred prop absent, pagination metadata present. Loading the
    // deferred prop group performs the follow-up partial reload the client would
    // make, resolving the tail: page 1 = oldest 10 days, ascending.
    get('/2025')->assertInertia(fn ($page) => $page
        ->missing('groups')
        ->where('currentPage', 1)
        ->where('lastPage', 2)
        ->loadDeferredProps(fn ($reload) => $reload
            ->has('groups', 10)
            ->where('groups.0.date', '2025-05-01')
            ->where('groups.9.date', '2025-05-10')));

    get('/2025?page=2')->assertInertia(fn ($page) => $page
        ->where('currentPage', 2)
        ->loadDeferredProps(fn ($reload) => $reload
            ->has('groups', 2)
            ->where('groups.0.date', '2025-05-11')));
});

it('serves the month tail and photos strip', function () {
    Storage::fake('public');
    $note = Note::factory()->create(['occurred_at' => '2025-05-03 10:00:00']);
    $note->addMediaFromString(yearPhotoJpegBytes())->usingFileName('note.jpg')->toMediaCollection('photos');

    get('/2025/05')->assertInertia(fn ($page) => $page
        ->component('Month')
        ->missing('groups')
        ->where('currentPage', 1)
        ->where('lastPage', 1)
        ->has('photos', 1)
        ->has('photos.0.src')
        ->loadDeferredProps(fn ($reload) => $reload
            ->has('groups', 1)
            ->where('groups.0.date', '2025-05-03')));
});

it('shows every photo in the month, uncapped', function () {
    Storage::fake('public');
    $note = Note::factory()->create(['occurred_at' => '2025-05-03 10:00:00']);

    foreach (range(1, 13) as $i) {
        $note->addMediaFromString(yearPhotoJpegBytes())->usingFileName("note-{$i}.jpg")->toMediaCollection('photos');
    }

    get('/2025/05')->assertInertia(fn ($page) => $page
        ->component('Month')
        ->has('photos', 13));
});
