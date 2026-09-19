<?php

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportLink;
use App\Enums\ExportFormat;
use App\Enums\TimelineType;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\Formats\MarkdownFormat;
use App\Support\PortableText;

it('renders a flight as markdown with front matter and fields', function () {
    $data = ExportPresenter::for(krkToLgw());
    $md = Formats::find($data, ExportFormat::Md)->render($data);

    expect($md)->toStartWith('---')
        ->and($md)->toContain('type: flight')
        ->and($md)->toContain("Distance: '876 miles'")
        ->and($md)->toContain('## See also')
        ->and($md)->not->toContain('## Other formats');
});

it('renders a locked entry as header only, with no fields, body or links', function () {
    $data = new ExportData(
        type: TimelineType::Note,
        url: 'https://example.test/secret',
        title: 'A private note',
        summary: 'A summary that should not leak',
        occurred: null,
        fields: [ExportField::make('body_word_count', 'Word count', '42', 42)],
        links: [ExportLink::make('tag', 'Tag', 'Secret tag', 'https://example.test/tags/secret')],
        body: [PortableText::block('The confidential contents of this note.')],
        locked: true,
    );

    $md = (new MarkdownFormat)->render($data);

    expect($md)->toContain('# A private note')
        ->and($md)->not->toContain('Word count')
        ->and($md)->not->toContain('42')
        ->and($md)->not->toContain('## See also')
        ->and($md)->not->toContain('## Other formats')
        ->and($md)->not->toContain('Secret tag')
        ->and($md)->not->toContain('https://example.test/tags/secret')
        ->and($md)->not->toContain('confidential')
        ->and($md)->not->toContain('should not leak');
});
