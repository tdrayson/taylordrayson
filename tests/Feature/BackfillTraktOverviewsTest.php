<?php

use App\Models\Film;
use App\Models\TvEpisode;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config()->set('services.trakt.client_id', 'k');
    config()->set('services.trakt.username', 'taylor');

    Saloon::fake(['*' => function ($request) {
        $first = $request->query()->get('page') == 1;

        return match (true) {
            str_contains($request->getUrl(), '/history/movies') => MockResponse::make($first ? [
                ['id' => 11, 'movie' => ['overview' => 'Film overview']],
                ['id' => 12, 'movie' => ['overview' => 'Should not replace']],
            ] : [], 200),
            str_contains($request->getUrl(), '/history/episodes') => MockResponse::make($first ? [
                ['id' => 21, 'episode' => ['overview' => 'Episode overview']],
                ['id' => 22, 'episode' => ['overview' => null]],
            ] : [], 200),
            default => MockResponse::make([], 200),
        };
    }]);
});

it('fills blank overviews from trakt history when applied', function () {
    $blank = Film::factory()->create(['source' => 'trakt', 'source_id' => '11', 'overview' => null]);
    $kept = Film::factory()->create(['source' => 'trakt', 'source_id' => '12', 'overview' => 'Mine']);
    $episode = TvEpisode::factory()->create(['source' => 'trakt', 'source_id' => '21', 'overview' => null]);
    $empty = TvEpisode::factory()->create(['source' => 'trakt', 'source_id' => '22', 'overview' => null]);

    $this->artisan('trakt:backfill-overviews', ['--apply' => true])->assertSuccessful();

    expect($blank->fresh()->overview)->toBe('Film overview')
        ->and($kept->fresh()->overview)->toBe('Mine')
        ->and($episode->fresh()->overview)->toBe('Episode overview')
        ->and($empty->fresh()->overview)->toBeNull();
});

it('writes nothing without --apply', function () {
    $film = Film::factory()->create(['source' => 'trakt', 'source_id' => '11', 'overview' => null]);

    $this->artisan('trakt:backfill-overviews')->assertSuccessful();

    expect($film->fresh()->overview)->toBeNull();
});
