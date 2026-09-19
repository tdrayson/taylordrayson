<?php

use App\Enums\ExportFormat;
use App\Models\Article;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Support\PortableText;
use Illuminate\Support\Facades\Queue;

it('prints an article as its typeset page, with the title and body wrapped to the column, but not the summary', function () {
    $article = Article::factory()->create([
        'title' => 'How I Built This Timeline',
        'excerpt' => 'A tour of the architecture.',
        'content' => [PortableText::block('It started with a spreadsheet, one column at a time.')],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($article);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('How I Built This Timeline')
        ->and($txt)->toContain('It started with a spreadsheet, one column')
        // The excerpt is usually the body's own opening paragraph, so the
        // typeset page prints the body only: .json, .md and .mf2 still carry
        // the summary for feeds and link previews.
        ->and($txt)->not->toContain('A tour of the architecture.')
        ->and(max(array_map('mb_strlen', explode("\n", $txt))))->toBeLessThanOrEqual(46);
});

it('keeps a pasted link intact on one line rather than cutting it mid-character', function () {
    Queue::fake();

    $url = 'https://example.com/a-very-long-path-segment-that-exceeds-forty-six-characters-total';

    $article = Article::factory()->create([
        'content' => [PortableText::block("Check this out {$url} end.")],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($article);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain($url);
});

it('prints only the title and rule when an article has no content', function () {
    $article = Article::factory()->create([
        'excerpt' => 'A tour of the architecture.',
        'content' => [],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($article);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect(rtrim($txt))->toEndWith(str_repeat('=', 46))
        ->and($txt)->not->toContain('A tour of the architecture.');
});
