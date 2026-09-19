<?php

use App\Data\ExportData;
use App\Enums\ExportFormat;
use App\Enums\TimelineType;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\Formats\IcsFormat;

it('renders a cross timezone flight with both zones named', function () {
    $data = ExportPresenter::for(krkToLgw());
    $ics = Formats::find($data, ExportFormat::Ics)->render($data, []);

    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->and($ics)->toContain('BEGIN:VEVENT')
        ->and($ics)->toContain('Europe/Warsaw')
        ->and($ics)->toContain('Europe/London')
        ->and($ics)->toContain('END:VCALENDAR');
});

it('folds long lines and ends them with crlf', function () {
    $data = ExportPresenter::for(krkToLgw());
    $ics = (new IcsFormat)->render($data, []);

    expect($ics)->toContain("\r\n");

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});

it('gives the event a stable uid derived from the entry url', function () {
    $data = ExportPresenter::for(krkToLgw());
    $ics = (new IcsFormat)->render($data, []);

    // RFC 5545 folds long lines at 75 octets; unfold before asserting the UID's content.
    $unfolded = str_replace("\r\n ", '', $ics);

    expect($unfolded)->toContain('UID:'.$data->url);
});

it('is unavailable for an export with no span', function () {
    $data = ExportPresenter::for(krkToLgw());
    $withoutSpan = new ExportData(
        type: $data->type, url: $data->url, title: $data->title, summary: null,
        occurred: null, fields: $data->fields, links: [],
    );

    expect((new IcsFormat)->supports($withoutSpan))->toBeFalse()
        ->and(array_key_exists('ics', Formats::for($withoutSpan)))->toBeFalse();
});

it('refuses ics for a locked export even if it carries a span aspect', function () {
    $data = ExportPresenter::for(krkToLgw());
    $locked = new ExportData(
        type: $data->type, url: $data->url, title: $data->title, summary: null,
        occurred: null, fields: $data->fields, links: [], aspects: $data->aspects, locked: true,
    );

    expect((new IcsFormat)->supports($locked))->toBeFalse();
});

it('refuses ics for a note with no span aspect at all', function () {
    $note = new ExportData(
        type: TimelineType::Note, url: 'https://example.test/x', title: 'A note',
        summary: null, occurred: null, fields: [], links: [],
    );

    expect((new IcsFormat)->supports($note))->toBeFalse();
});
