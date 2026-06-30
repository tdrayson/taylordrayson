<?php

use App\Services\ThisWeekWith;
use Illuminate\Support\Facades\Http;

it('walks every page and returns the raw episodes', function () {
    Http::fake([
        '*wp-json/podcast/v1/episodes*' => Http::sequence()
            ->push(['episodes' => [['episode_number' => 1], ['episode_number' => 2]], 'total_pages' => 2])
            ->push(['episodes' => [['episode_number' => 3]], 'total_pages' => 2]),
    ]);

    $episodes = app(ThisWeekWith::class)->episodes(2);

    expect($episodes)->toHaveCount(3);
    expect(array_column($episodes, 'episode_number'))->toBe([1, 2, 3]);
    Http::assertSentCount(2);
});

it('throws when a page request fails', function () {
    Http::fake(['*wp-json/podcast/v1/episodes*' => Http::response('down', 503)]);

    app(ThisWeekWith::class)->episodes();
})->throws(RuntimeException::class);
