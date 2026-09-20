<?php

use App\Enums\ExportFormat;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('renders a flight as markdown with front matter and fields', function () {
    $data = ExportPresenter::for(krkToLgw());
    $md = Formats::find($data, ExportFormat::Md)->render($data);

    expect($md)->toStartWith('---')
        ->and($md)->toContain('type: flight')
        ->and($md)->toContain("Distance: '876 miles'")
        ->and($md)->toContain('## See also')
        ->and($md)->not->toContain('## Other formats');
});
