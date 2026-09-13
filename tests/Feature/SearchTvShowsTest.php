<?php

use App\Models\TvEpisode;
use App\Models\TvShow;
use App\Search\SuggestSearch;

use function Pest\Laravel\postJson;

function watchedTvShow(string $title, string $slug): TvShow
{
    $tvShow = TvShow::create(['trakt_id' => crc32($slug), 'title' => $title, 'slug' => $slug]);

    TvEpisode::factory()->create([
        'title' => 'An episode',
        'tv_show_id' => $tvShow->id,
        'occurred_at' => '2026-08-01 20:00:00',
    ]);

    return $tvShow;
}

it('offers a watched show as a palette destination', function () {
    watchedTvShow('Severance', 'severance');

    $destinations = app(SuggestSearch::class)('sever')['destinations'];

    expect($destinations)->toHaveCount(1)
        ->and($destinations[0]['label'])->toBe('Severance')
        ->and($destinations[0]['section'])->toBe('TV')
        ->and($destinations[0]['type'])->toBe('tv-episode')
        ->and($destinations[0]['url'])->toBe('/tv-shows/severance');
});

it('leaves out a show with nothing watched', function () {
    TvShow::create(['trakt_id' => 999, 'title' => 'Unwatched Show', 'slug' => 'unwatched-show']);

    expect(app(SuggestSearch::class)('unwatched')['destinations'])->toBeEmpty();
});

it('ranks a show above a category matching the same word', function () {
    watchedTvShow('The Office', 'the-office');

    $sections = array_column(app(SuggestSearch::class)('office')['destinations'], 'section');

    expect($sections[0])->toBe('TV');
});

it('filters episodes by the show they belong to', function () {
    $severance = watchedTvShow('Severance', 'severance');
    watchedTvShow('Breaking Bad', 'breaking-bad');

    $response = postJson('/search', [
        'type' => 'tv-episode',
        'match' => 'all',
        'conditions' => [['field' => 'show', 'operator' => 'contains', 'value' => 'Severance']],
    ]);

    $response->assertOk();

    expect(TvEpisode::where('tv_show_id', $severance->id)->count())->toBe(1);
});
