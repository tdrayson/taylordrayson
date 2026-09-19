<?php

use App\Enums\ExportFormat;
use App\Models\Sleep;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\NowExport;

it('prints now as its status board, with each field as a row under a boxed heading', function () {
    Sleep::factory()->create(['occurred_at' => today(), 'duration' => 27300, 'status' => 'published']);

    $data = (new NowExport)->present();
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('NOW')
        ->and($txt)->toContain('Last night')
        ->and($txt)->toContain('7h 35m')
        ->and(max(array_map('mb_strlen', explode("\n", $txt))))->toBeLessThanOrEqual(46);
});

it('drops the sleep row when there is no recent night', function () {
    $data = (new NowExport)->present();
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('Last night');
});
