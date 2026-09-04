<?php

use App\Services\ThisWeekWith;
use App\Services\ThisWeekWith\EpisodesRequest;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

it('walks every page and returns the raw episodes', function () {
    Saloon::fake([
        MockResponse::make(['episodes' => [['episode_number' => 1], ['episode_number' => 2]], 'total_pages' => 2]),
        MockResponse::make(['episodes' => [['episode_number' => 3]], 'total_pages' => 2]),
    ]);

    $episodes = iterator_to_array(app(ThisWeekWith::class)->episodes(2), false);

    expect($episodes)->toHaveCount(3);
    expect(array_column($episodes, 'episode_number'))->toBe([1, 2, 3]);
    Saloon::assertSentCount(2);
});

/**
 * The laziness is the point: it is what lets `podcast:sync` stop at the
 * episodes it already has instead of paging the whole back catalogue.
 */
it('does not request later pages when the caller stops early', function () {
    Saloon::fake([
        MockResponse::make(['episodes' => [['episode_number' => 1], ['episode_number' => 2]], 'total_pages' => 2]),
        MockResponse::make(['episodes' => [['episode_number' => 3]], 'total_pages' => 2]),
    ]);

    foreach (app(ThisWeekWith::class)->episodes(2) as $episode) {
        break;
    }

    Saloon::assertSentCount(1);
});

it('throws when a page request fails', function () {
    Saloon::fake([
        EpisodesRequest::class => MockResponse::make('down', 503),
    ]);

    iterator_to_array(app(ThisWeekWith::class)->episodes(), false);
})->throws(RuntimeException::class);
