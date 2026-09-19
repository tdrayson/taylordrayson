<?php

use App\Enums\ExportFormat;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a flight as a boarding pass', function () {
    $data = ExportPresenter::for(krkToLgw());
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('easyJet UK U2 8824')
        ->and($txt)->toContain('KRK  ->  LGW')
        ->and($txt)->toContain('Kraków')
        ->and($txt)->toContain('London')
        ->and($txt)->toContain('876 miles')
        ->and($txt)->toContain('2h 27m')
        ->and($txt)->not->toContain('1409785')
        ->and($txt)->not->toContain('8820');
});
