<?php

use App\Models\Article;
use App\Models\Note;
use App\Support\PortableText;

use function Pest\Laravel\get;

it('stores note content as a plain string', function () {
    $note = Note::factory()->create()->fresh();

    expect($note->content)->toBeString();
});

it('derives the note card title from the content string', function () {
    $note = Note::factory()->create([
        'content' => 'First line of the note becomes the title.',
    ]);

    expect($note->card()['title'])->toContain('First line of the note');
});

it('exposes note content as plain text on the detail page', function () {
    $note = Note::factory()->create([
        'content' => 'Plain text here',
    ]);

    $url = $note->occurred_at->format('Y/m/d').'/'.$note->slug();

    get("/{$url}")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Entry')
        ->where('entry.content', 'Plain text here')
    );
});

it('stores article content as a bare portable text node array', function () {
    $article = Article::factory()->create()->fresh();

    expect($article->content)->toBeArray();
    expect(PortableText::nodes($article->content))->toBe($article->content);
    expect($article->content[0]['_type'])->toBe('block');
});

it('derives the article card subtitle from portable text content', function () {
    $article = Article::factory()->create([
        'content' => [
            PortableText::block('Hello World', 'h2'),
            PortableText::block('A link here one two'),
        ],
        'excerpt' => 'Fallback excerpt',
    ]);

    expect($article->card()['subtitle'])->toBe('Hello World A link here one two');
});

it('falls back to the excerpt when portable text content has no text', function () {
    $article = Article::factory()->create([
        'content' => [],
        'excerpt' => 'Fallback excerpt',
    ]);

    expect($article->card()['subtitle'])->toBe('Fallback excerpt');
});
