<?php

use App\Models\Article;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Support\PortableText;

it('publishes an article as labelled fields in order, with its body as portable text', function () {
    $article = Article::factory()->create([
        'occurred_at' => '2026-09-13 08:00:00',
        'title' => 'How I Built This Timeline',
        'slug' => 'how-i-built-this-timeline',
        'excerpt' => 'A tour of the architecture.',
        'content' => [PortableText::block('It started with a spreadsheet.')],
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($article);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['article', 'excerpt'])
        ->and($export->field('article')->display)->toBe('How I Built This Timeline')
        ->and($export->field('excerpt')->display)->toBe('A tour of the architecture.')
        ->and($export->body)->toBe($article->content);

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('type', 'day');
});

it('offers neither geojson nor ics for an article', function () {
    $article = Article::factory()->create(['occurred_at' => '2026-09-13 08:00:00', 'status' => 'published']);

    $formats = array_keys(Formats::for(ExportPresenter::for($article)));

    expect($formats)->not->toContain('geojson')
        ->and($formats)->not->toContain('ics');
});

it('never leaks an id, a timestamp or a password', function () {
    $article = Article::factory()->create(['occurred_at' => '2026-09-13 08:00:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($article)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
