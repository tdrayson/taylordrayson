<?php

use App\Services\ThisWeekWith;
use Illuminate\Support\Facades\Http;

it('walks every page and returns the raw episodes', function () {
    Http::fake([
        '*wp-json/podcast/v1/episodes*' => Http::sequence()
            ->push(['episodes' => [['episode_number' => 1], ['episode_number' => 2]], 'total_pages' => 2])
            ->push(['episodes' => [['episode_number' => 3]], 'total_pages' => 2]),
    ]);

    $episodes = iterator_to_array(app(ThisWeekWith::class)->episodes(2), false);

    expect($episodes)->toHaveCount(3);
    expect(array_column($episodes, 'episode_number'))->toBe([1, 2, 3]);
    Http::assertSentCount(2);
});

/**
 * The laziness is the point: it is what lets `podcast:sync` stop at the
 * episodes it already has instead of paging the whole back catalogue.
 */
it('does not request later pages when the caller stops early', function () {
    Http::fake([
        '*wp-json/podcast/v1/episodes*' => Http::sequence()
            ->push(['episodes' => [['episode_number' => 1], ['episode_number' => 2]], 'total_pages' => 2])
            ->push(['episodes' => [['episode_number' => 3]], 'total_pages' => 2]),
    ]);

    foreach (app(ThisWeekWith::class)->episodes(2) as $episode) {
        break;
    }

    Http::assertSentCount(1);
});

it('throws when a page request fails', function () {
    Http::fake(['*wp-json/podcast/v1/episodes*' => Http::response('down', 503)]);

    iterator_to_array(app(ThisWeekWith::class)->episodes(), false);
})->throws(RuntimeException::class);
