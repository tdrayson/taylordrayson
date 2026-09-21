<?php

use App\Enums\ExportFormat;
use App\Models\Appearance;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints an appearance as its broadcast slate', function () {
    $appearance = Appearance::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'show_name' => 'Talk Devy To Me by Ryan Welcher',
        'title' => 'WP Wireframe Explained: No Compile Step, No Problem',
        'type' => 'livestream',
        'duration' => 6329,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($appearance);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('Talk Devy To Me by Ryan Welcher')
        ->and($txt)->toContain('WP Wireframe Explained: No Compile Step, No')
        ->and($txt)->toContain('KIND')
        ->and($txt)->toContain('Livestream')
        ->and($txt)->toContain('DURATION')
        ->and($txt)->toContain('1h 45m');
});

it('omits the duration row when an appearance carries no duration', function () {
    $appearance = Appearance::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'duration' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($appearance);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('DURATION');
});
