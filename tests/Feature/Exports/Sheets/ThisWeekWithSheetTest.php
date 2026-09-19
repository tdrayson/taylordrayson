<?php

use App\Enums\ExportFormat;
use App\Models\ThisWeekWith;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a This Week With episode as its rundown, with the SxxEyy banner', function () {
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
        // A real episode URL is far longer than the 46-character sheet width
        // and would only mangle mid-word; .md and .json carry the real links.
        ->and($txt)->not->toContain('https://');
});

it('omits the duration row when an episode carries no duration', function () {
    $episode = ThisWeekWith::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'duration' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($episode);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('DURATION');
});
