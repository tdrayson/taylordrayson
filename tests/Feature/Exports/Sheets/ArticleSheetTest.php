<?php

use App\Enums\ExportFormat;
use App\Models\Article;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Support\PortableText;

it('prints an article as its typeset page, with the title, summary and body wrapped to the column', function () {
    $article = Article::factory()->create([
        'title' => 'How I Built This Timeline',
        'excerpt' => 'A tour of the architecture.',
        'content' => [PortableText::block('It started with a spreadsheet, one column at a time.')],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($article);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('How I Built This Timeline')
        ->and($txt)->toContain('A tour of the architecture.')
        ->and($txt)->toContain('It started with a spreadsheet, one column')
        ->and(max(array_map('mb_strlen', explode("\n", $txt))))->toBeLessThanOrEqual(46);
});

it('drops the body block entirely when an article has no content', function () {
    $article = Article::factory()->create([
        'excerpt' => 'A tour of the architecture.',
        'content' => [],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($article);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect(rtrim($txt))->toEndWith('A tour of the architecture.');
});
