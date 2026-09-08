<?php

use App\Models\Article;
use App\Models\Note;
use App\Presenters\CardPresenter;
use App\Support\PortableText;

use function Pest\Laravel\get;

it('resolves tags in the content a page renders', function () {
    Note::factory()->count(3)->create();
    $article = Article::factory()->create([
        'published' => true,
        // Scoped to notes: an untyped count would also catch the article's own
        // timeline entry once it's published, making the expected number flaky.
        'content' => contentTagging('entries.count', ['type' => 'note']),
    ]);

    get('/'.$article->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entry.content', fn ($content) => PortableText::plainText($content) === 'I have logged 3 things.'));
});

it('resolves tags in the card subtitle', function () {
    Note::factory()->count(3)->create();
    $article = Article::factory()->create([
        'published' => true,
        'content' => contentTagging('entries.count', ['type' => 'note']),
    ]);

    expect(CardPresenter::for($article->fresh())->subtitle)
        ->toContain('I have logged 3 things.');
});

it('memoises the resolution per model instance', function () {
    $article = Article::factory()->create(['content' => contentTagging('entries.count')]);

    expect($article->resolvedContent())->toBe($article->resolvedContent());
});
