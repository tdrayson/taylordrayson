<?php

use App\Models\Article;
use App\Models\Note;
use App\Support\EditorJs;

use function Pest\Laravel\get;

it('flattens an editor.js document to plain text, stripping inline html', function () {
    $document = EditorJs::document([
        ['type' => 'header', 'data' => ['text' => 'Hello <b>World</b>', 'level' => 2]],
        ['type' => 'paragraph', 'data' => ['text' => 'A <a href="#">link</a> here']],
        ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['one', 'two']]],
    ]);

    expect(EditorJs::plainText($document))->toBe('Hello World A link here one two');
    expect(EditorJs::plainText(null))->toBe('');
    expect(EditorJs::plainText('not json'))->toBe('');
});

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

it('stores article content as an editor.js document', function () {
    $article = Article::factory()->create()->fresh();

    expect($article->content['blocks'])->toBeArray();
});
