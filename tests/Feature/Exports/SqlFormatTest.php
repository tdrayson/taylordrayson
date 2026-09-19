<?php

use App\Data\ExportData;
use App\Data\ExportField;
use App\Enums\ExportFormat;
use App\Enums\TimelineType;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\Formats\SqlFormat;

it('renders a flight as the insert that made it', function () {
    $data = ExportPresenter::for(krkToLgw());
    $sql = Formats::find($data, ExportFormat::Sql)->render($data, []);

    expect($sql)->toContain('INSERT INTO flights')
        ->and($sql)->toContain('distance')
        ->and($sql)->toContain('1409785')
        ->and($sql)->not->toContain('password')
        ->and($sql)->toContain("-- columns are the export's published fields, not the flights table's schema");
});

it('renders a structured field by its display string, not a json blob', function () {
    $data = new ExportData(
        type: TimelineType::Note,
        url: 'https://example.test/x',
        title: 'A note',
        summary: null,
        occurred: null,
        fields: [
            ExportField::make('place', 'Place', 'Kraków John Paul II International Airport (KRK)', ['iata' => 'KRK', 'lat' => 50.077702]),
            ExportField::make('distance', 'Distance', '876 miles', 1409785),
        ],
        links: [],
    );

    $sql = (new SqlFormat)->render($data, []);

    expect($sql)->toContain("'Kraków John Paul II International Airport (KRK)'")
        ->and($sql)->not->toContain('"iata"')
        ->and($sql)->toContain('1409785')
        ->and($sql)->not->toContain('876 miles');
});

it('escapes a quote rather than breaking out of the string', function () {
    $data = new ExportData(
        type: TimelineType::Note,
        url: 'https://example.test/x',
        title: 'A note',
        summary: null,
        occurred: null,
        fields: [ExportField::make('body', 'Body', "It's fine", "It's fine")],
        links: [],
    );

    $sql = (new SqlFormat)->render($data, []);

    expect($sql)->toContain("'It''s fine'")
        ->and($sql)->not->toContain("'It's fine'")
        ->and(substr_count($sql, 'INSERT INTO'))->toBe(1)
        ->and(substr_count($sql, 'VALUES ('))->toBe(1);
});

it('refuses sql for a locked export', function () {
    $locked = new ExportData(
        type: TimelineType::Note, url: 'https://example.test/x', title: 'Locked',
        summary: null, occurred: null, fields: [], links: [], locked: true,
    );

    expect((new SqlFormat)->supports($locked))->toBeFalse();
});

it('refuses sql for an export with no fields', function () {
    $empty = new ExportData(
        type: TimelineType::Note, url: 'https://example.test/x', title: 'Empty',
        summary: null, occurred: null, fields: [], links: [],
    );

    expect((new SqlFormat)->supports($empty))->toBeFalse();
});
