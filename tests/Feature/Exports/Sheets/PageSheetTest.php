<?php

use App\Enums\ExportFormat;
use App\Models\Page;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Support\PortableText;

it('prints a page as its typeset page, with the title, summary and body wrapped to the column', function () {
    $page = Page::factory()->create([
        'title' => 'Colophon',
        'excerpt' => 'How this site is built.',
        'content' => PortableText::fromPlainText('The details of the build, spelled out plainly.'),
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($page);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('Colophon')
        ->and($txt)->toContain('How this site is built.')
        ->and($txt)->toContain('The details of the build, spelled out')
        ->and(max(array_map('mb_strlen', explode("\n", $txt))))->toBeLessThanOrEqual(46);
});

it('drops the body block entirely when a page has no content', function () {
    $page = Page::factory()->create([
        'excerpt' => 'How this site is built.',
        'content' => [],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($page);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect(rtrim($txt))->toEndWith('How this site is built.');
});
