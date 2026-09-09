<?php

use App\Models\Article;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

/** Park an upload the way the editor's image block does. */
function parkedImage(): string
{
    return test()->postJson('/media/pending', ['file' => UploadedFile::fake()->image('shot.jpg', 900, 600)])
        ->assertOk()
        ->json('data.url');
}

/** Park a zip the way the editor's file block does. */
function parkedFile(): string
{
    return test()->postJson('/media/pending', ['file' => zipUpload()])
        ->assertOk()
        ->json('data.url');
}

/** A document holding one image at the given url. */
function documentWith(?string $url): array
{
    $blocks = [['_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'children' => [['_type' => 'span', '_key' => 's1', 'text' => 'Words', 'marks' => []]]]];

    return $url === null ? $blocks : [...$blocks, ['_type' => 'image', '_key' => 'i1', 'url' => $url]];
}

it('attaches an image dropped in the body and repoints the document at it', function () {
    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", [
        'title' => $article->title,
        'slug' => $article->slug,
        'content' => documentWith(parkedImage()),
    ])->assertRedirect();

    $article->refresh();
    $image = collect($article->content)->firstWhere('_type', 'image');

    expect($article->getMedia('body'))->toHaveCount(1)
        // No longer the parked URL: that one stops resolving once it is consumed.
        ->and($image['url'])->not->toContain('/media/pending/')
        ->and($image['url'])->toBe($article->getFirstMedia('body')->getUrl());
});

it('deletes a body image once the document stops referencing it', function () {
    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", [
        'title' => $article->title,
        'slug' => $article->slug,
        'content' => documentWith(parkedImage()),
    ])->assertRedirect();

    $media = $article->refresh()->getFirstMedia('body');
    $path = $media->getPath();

    expect(is_file($path))->toBeTrue();

    $this->patch("/entries/article/{$article->id}", [
        'title' => $article->title,
        'slug' => $article->slug,
        'content' => documentWith(null),
    ])->assertRedirect();

    expect($article->refresh()->getMedia('body'))->toHaveCount(0)
        ->and(is_file($path))->toBeFalse();
});

it('leaves body images alone when the body was not part of the update', function () {
    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", [
        'title' => $article->title,
        'slug' => $article->slug,
        'content' => documentWith(parkedImage()),
    ])->assertRedirect();

    expect($article->refresh()->getMedia('body'))->toHaveCount(1);

    $this->patch("/entries/article/{$article->id}", [
        'title' => 'A new title',
        'slug' => $article->slug,
    ])->assertRedirect();

    expect($article->refresh()->getMedia('body'))->toHaveCount(1);
});

/** A document holding one uploaded file node at the given url. */
function documentWithFile(string $url): array
{
    return [
        ['_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'children' => [['_type' => 'span', '_key' => 's1', 'text' => 'Words', 'marks' => []]]],
        ['_type' => 'file', '_key' => 'f1', 'source' => 'upload', 'url' => $url],
    ];
}

it('attaches a file dropped in the body and labels it from the stored media', function () {
    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", [
        'title' => $article->title,
        'slug' => $article->slug,
        'content' => documentWithFile(parkedFile()),
    ])->assertRedirect();

    $article->refresh();
    $file = collect($article->content)->firstWhere('_type', 'file');
    $media = $article->getFirstMedia('body');

    expect($article->getMedia('body'))->toHaveCount(1)
        ->and($file['url'])->toBe($media->getUrl())
        ->and($file['name'])->toBe('bundle.zip')
        ->and($file['mime'])->toBe($media->mime_type)
        ->and($file['size'])->toBe($media->size);
});

it('keeps an attached file when the entry is saved a second time', function () {
    $article = Article::factory()->create();

    $this->patch("/entries/article/{$article->id}", [
        'title' => $article->title,
        'slug' => $article->slug,
        'content' => documentWithFile(parkedFile()),
    ])->assertRedirect();

    $article->refresh();
    $path = $article->getFirstMedia('body')->getPath();

    // The second save is the one that used to bin it: the document still points
    // at the file, but only image nodes were counted as still referenced.
    $this->patch("/entries/article/{$article->id}", [
        'title' => 'A new title',
        'slug' => $article->slug,
        'content' => $article->content,
    ])->assertRedirect();

    expect($article->refresh()->getMedia('body'))->toHaveCount(1)
        ->and(is_file($path))->toBeTrue();
});
