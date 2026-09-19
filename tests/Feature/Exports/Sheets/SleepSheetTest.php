<?php

use App\Enums\ExportFormat;
use App\Models\Sleep;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a night of sleep as a hypnogram, with a bar per stage', function () {
    $sleep = Sleep::factory()->create([
        'started_at' => '2026-09-12 23:00:00',
        'occurred_at' => '2026-09-13 06:30:00',
        'duration' => 27000,
        'deep' => 5400,
        'core' => 14400,
        'rem' => 6300,
        'awake' => 900,
        'score' => 82,
        'source' => 'apple_watch',
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($sleep);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('DEEP')
        ->and($txt)->toContain('20%')
        ->and($txt)->toContain('82 out of 100')
        ->and($txt)->toContain('#')
        // 5400 is the raw deep-sleep seconds; the sheet must print "20%".
        ->and($txt)->not->toContain('5400');
});
