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
