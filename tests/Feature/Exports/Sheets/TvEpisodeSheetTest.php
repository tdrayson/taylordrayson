<?php

use App\Enums\ExportFormat;
use App\Models\TvEpisode;
use App\Models\TvShow;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a TV episode as its ticket stub, banner as the show and title centred', function () {
    $show = TvShow::factory()->create(['title' => 'Crime Scene: The Vanishing at the Cecil Hotel']);
    $episode = TvEpisode::factory()->create([
        'tv_show_id' => $show->id,
        'occurred_at' => '2026-09-13 20:00:00',
        'title' => 'Down the Rabbit Hole',
        'rating' => 8,
        'meta' => ['season' => 1, 'episode' => 3],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($episode);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('Crime Scene: The Vanishing at the Cecil Hotel')
        ->and($txt)->toContain('Down the Rabbit Hole')
        ->and($txt)->toContain('SEASON')
        ->and($txt)->toContain('1')
        ->and($txt)->toContain('NUMBER')
        ->and($txt)->toContain('3')
        ->and($txt)->toContain('8 out of 10');
});

it('omits the rating row when a TV episode carries no rating', function () {
    $episode = TvEpisode::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'rating' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($episode);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('RATING');
});
