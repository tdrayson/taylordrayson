<?php

use App\Enums\ExportFormat;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('renders a flight as markdown with front matter, fields and a trail', function () {
    $data = ExportPresenter::for(krkToLgw());
    $md = Formats::find($data, ExportFormat::Md)->render($data, ['json' => 'https://example.test/x.json']);

    expect($md)->toStartWith('---')
        ->and($md)->toContain('type: flight')
        ->and($md)->toContain("Distance: '876 miles'")
        ->and($md)->toContain('## See also')
        ->and($md)->toContain('[json](https://example.test/x.json)')
        ->and($md)->not->toContain('[https://example.test/x.json](json)');
});
