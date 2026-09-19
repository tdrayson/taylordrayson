<?php

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportLink;
use App\Enums\ExportFormat;
use App\Enums\TimelineType;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use Symfony\Component\Yaml\Yaml;

it('offers only the formats built so far for a flight', function () {
    $available = array_keys(Formats::for(ExportPresenter::for(krkToLgw())));

    expect($available)->toContain('json', 'yaml')
        ->and($available)->not->toContain('txt', 'md', 'mf2', 'sql', 'ics', 'geojson');
});

it('renders a flight as json carrying both display and raw', function () {
    $data = ExportPresenter::for(krkToLgw());
    $json = json_decode(Formats::find($data, ExportFormat::Json)->render($data, ['md' => 'https://example.test/x.md']), true);

    expect($json['type'])->toBe('flight')
        ->and($json['url'])->toStartWith('http')
        ->and($json['fields'][0])->toHaveKeys(['key', 'label', 'display', 'raw'])
        ->and($json['formats'])->toBe(['md' => 'https://example.test/x.md']);
});

it('renders the same object as yaml', function () {
    $data = ExportPresenter::for(krkToLgw());
    $yaml = Formats::find($data, ExportFormat::Yaml)->render($data, []);

    expect($yaml)->toContain('type: flight')
        ->and(Yaml::parse($yaml)['fields'][0]['key'])->toBe('flight');
});

function lockedNoteWithFieldsAndLinks(): ExportData
{
    return new ExportData(
        type: TimelineType::Note,
        url: 'https://example.test/secret',
        title: 'A private note',
        summary: null,
        occurred: null,
        fields: [ExportField::make('body_word_count', 'Word count', '42', 42)],
        links: [ExportLink::make('tag', 'Tag', 'Secret tag', 'https://example.test/tags/secret')],
        locked: true,
    );
}

it('renders a locked entry as json with no fields or links', function () {
    $data = lockedNoteWithFieldsAndLinks();
    $rendered = Formats::find($data, ExportFormat::Json)->render($data, []);
    $json = json_decode($rendered, true);

    expect($json)->not->toHaveKey('fields')
        ->and($json)->not->toHaveKey('links')
        ->and($json['locked'])->toBeTrue()
        ->and($rendered)->not->toContain('Word count')
        ->and($rendered)->not->toContain('Secret tag');
});

it('renders a locked entry as yaml with no fields or links', function () {
    $data = lockedNoteWithFieldsAndLinks();
    $yaml = Formats::find($data, ExportFormat::Yaml)->render($data, []);
    $parsed = Yaml::parse($yaml);

    expect($parsed)->not->toHaveKey('fields')
        ->and($parsed)->not->toHaveKey('links')
        ->and($parsed['locked'])->toBeTrue()
        ->and($yaml)->not->toContain('Word count')
        ->and($yaml)->not->toContain('Secret tag');
});
