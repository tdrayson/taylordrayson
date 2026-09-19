<?php

use App\Enums\ExportFormat;
use App\Models\Page;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Support\PortableText;
use Illuminate\Support\Facades\Queue;

it('prints a page as its typeset page, with the title and body wrapped to the column, but not the summary', function () {
    $page = Page::factory()->create([
        'title' => 'Colophon',
        'excerpt' => 'How this site is built.',
        'content' => PortableText::fromPlainText('The details of the build, spelled out plainly.'),
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($page);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('Colophon')
        ->and($txt)->toContain('The details of the build, spelled out')
        ->and($txt)->not->toContain('How this site is built.')
        ->and(max(array_map('mb_strlen', explode("\n", $txt))))->toBeLessThanOrEqual(46);
});

it('keeps a pasted link intact on one line rather than cutting it mid-character', function () {
    Queue::fake();

    $url = 'https://example.com/a-very-long-path-segment-that-exceeds-forty-six-characters-total';

    $page = Page::factory()->create([
        'content' => PortableText::fromPlainText("Check this out {$url} end."),
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($page);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain($url);
});

it('prints only the title and rule when a page has no content', function () {
    $page = Page::factory()->create([
        'excerpt' => 'How this site is built.',
        'content' => [],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($page);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect(rtrim($txt))->toEndWith(str_repeat('=', 46))
        ->and($txt)->not->toContain('How this site is built.');
});
