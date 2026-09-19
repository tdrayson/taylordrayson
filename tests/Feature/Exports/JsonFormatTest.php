<?php

use App\Enums\ExportFormat;
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
