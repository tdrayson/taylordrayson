<?php

use App\Models\Article;
use App\Models\Note;
use App\Presenters\CardPresenter;
use App\Support\PortableText;

it('derives the note card title from the content string', function () {
    $note = Note::factory()->create([
        'content' => 'First line of the note becomes the title.',
    ]);

    expect(CardPresenter::for($note)->title)->toContain('First line of the note');
});

it('derives the article card subtitle from portable text content', function () {
    $article = Article::factory()->create([
        'content' => [
            PortableText::block('Hello World', 'h2'),
            PortableText::block('A link here one two'),
        ],
        'excerpt' => 'Fallback excerpt',
    ]);

    expect(CardPresenter::for($article)->subtitle)->toBe('Hello World A link here one two');
});

it('falls back to the excerpt when portable text content has no text', function () {
    $article = Article::factory()->create([
        'content' => [],
        'excerpt' => 'Fallback excerpt',
    ]);

    expect(CardPresenter::for($article)->subtitle)->toBe('Fallback excerpt');
});
