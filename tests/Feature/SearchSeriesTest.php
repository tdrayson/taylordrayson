<?php

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Series;
use App\Search\SuggestSearch;

use function Pest\Laravel\postJson;

function watchedSeries(string $title, string $slug): Series
{
    $series = Series::create(['trakt_id' => crc32($slug), 'title' => $title, 'slug' => $slug]);

    Media::factory()->create([
        'type' => MediaType::TvEpisode,
        'title' => 'An episode',
        'series_id' => $series->id,
        'occurred_at' => '2026-08-01 20:00:00',
    ]);

    return $series;
}

it('offers a watched show as a palette destination', function () {
    watchedSeries('Severance', 'severance');

    $destinations = app(SuggestSearch::class)('sever')['destinations'];

    expect($destinations)->toHaveCount(1)
        ->and($destinations[0]['label'])->toBe('Severance')
        ->and($destinations[0]['section'])->toBe('TV')
        ->and($destinations[0]['url'])->toBe('/media/tv/severance');
});

it('leaves out a show with nothing watched', function () {
    Series::create(['trakt_id' => 999, 'title' => 'Unwatched Show', 'slug' => 'unwatched-show']);

    expect(app(SuggestSearch::class)('unwatched')['destinations'])->toBeEmpty();
});

it('ranks a show above a category matching the same word', function () {
    watchedSeries('The Office', 'the-office');

    $sections = array_column(app(SuggestSearch::class)('office')['destinations'], 'section');

    expect($sections[0])->toBe('TV');
});

it('filters media by the show an episode belongs to', function () {
    $severance = watchedSeries('Severance', 'severance');
    watchedSeries('Breaking Bad', 'breaking-bad');

    $response = postJson('/search', [
        'type' => 'media',
        'match' => 'all',
        'conditions' => [['field' => 'show', 'operator' => 'contains', 'value' => 'Severance']],
    ]);

    $response->assertOk();

    expect(Media::where('series_id', $severance->id)->count())->toBe(1);
});
