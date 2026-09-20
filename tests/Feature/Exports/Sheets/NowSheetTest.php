<?php

use App\Enums\ExportFormat;
use App\Models\Sleep;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\NowExport;

it('prints now as its status board, each reading a row under its section', function () {
    Sleep::factory()->create(['occurred_at' => today(), 'duration' => 27300, 'status' => 'published']);

    $data = (new NowExport)->present();
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    // The section is what gives a bare duration its meaning, so both matter.
    expect($txt)->toContain('NOW')
        ->and($txt)->toContain('LAST NIGHT')
        ->and($txt)->toContain('SLEPT')
        ->and($txt)->toContain('7h 35m')
        ->and(max(array_map('mb_strwidth', explode("\n", $txt))))->toBeLessThanOrEqual(46);
});

it('drops the sleep row when there is no recent night', function () {
    $data = (new NowExport)->present();
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('LAST NIGHT')
        ->and($txt)->not->toContain('SLEPT');
});
