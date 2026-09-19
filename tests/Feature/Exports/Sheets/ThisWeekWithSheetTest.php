<?php

use App\Enums\ExportFormat;
use App\Models\ThisWeekWith;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a This Week With episode as its rundown, with the SxxEyy banner and both links', function () {
    $episode = ThisWeekWith::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'season_number' => 6,
        'episode_number' => 226,
        'topic' => 'New Flat & Setup - Moving Logistics & Boxes - Flat-Pack Assembly & DIY',
        'duration' => 1249,
        'audio_url' => 'https://media.blubrry.com/thisweekwith/example-episode-226.mp3',
        'video_url' => 'https://www.youtube.com/live/kaBebzaWJw8',
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($episode);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('S6E226')
        ->and($txt)->toContain('New Flat & Setup')
        ->and($txt)->toContain('DURATION')
        ->and($txt)->toContain('20m')
        ->and($txt)->toContain('LISTEN')
        ->and($txt)->toContain('WATCH')
        ->and($txt)->toContain('https://www.youtube.com/live/kaBebzaWJw8');
});

it('omits listen and watch rows when an episode carries no links', function () {
    $episode = ThisWeekWith::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'audio_url' => null,
        'video_url' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($episode);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('LISTEN')
        ->and($txt)->not->toContain('WATCH');
});
