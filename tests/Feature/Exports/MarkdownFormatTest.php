<?php

use App\Enums\ExportFormat;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use Symfony\Component\Yaml\Yaml;

it('renders a flight as markdown with front matter and fields', function () {
    $data = ExportPresenter::for(krkToLgw());
    $md = Formats::find($data, ExportFormat::Md)->render($data);

    expect($md)->toStartWith('---')
        ->and($md)->toContain('type: flight')
        ->and($md)->toContain("distance: '876 miles'")
        ->and($md)->toContain('## See also')
        ->and($md)->not->toContain('## Other formats');
});

it('keeps every field in the front matter, whatever its label or key collides with', function () {
    $data = ExportPresenter::for(krkToLgw());
    $md = Formats::find($data, ExportFormat::Md)->render($data);
    $matter = Yaml::parse(explode("---\n", $md)[1]);

    // Origin and destination share the labels "Code" and "City", so a
    // label-keyed front matter kept only the destination.
    expect($matter['fields']['origin_code'])->toBe('KRK')
        ->and($matter['fields']['destination_code'])->toBe('LGW')
        ->and($matter['fields']['origin_city'])->not->toBe($matter['fields']['destination_city'])
        // The document's own date is the ISO instant; the flight's `date`
        // field is a display string. Flattening let one overwrite the other.
        ->and($matter['date'])->toBe($data->occurred->iso)
        ->and($matter['fields']['date'])->not->toBe($matter['date'])
        // Nothing dropped on the way into the front matter.
        ->and($matter['fields'])->toHaveCount(count($data->fields));
});
