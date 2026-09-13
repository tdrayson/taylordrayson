<?php

use App\Models\Article;
use App\Models\Note;
use App\Models\User;
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

it('exposes the unresolved document to a signed-in editor, not the resolved one', function () {
    $article = Article::factory()->create([
        'published' => true,
        'content' => contentTagging('entries.count', ['type' => 'note']),
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/'.$article->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // The renderer's own copy stays resolved.
            ->where('entry.content', fn ($content) => PortableText::plainText($content) === 'I have logged 0 things.')
            // The editor's copy still carries the dynamicTag node, so saving
            // the form unchanged cannot bake the resolved text over it.
            ->where('entry.rawContent.0.children.1._type', 'dynamicTag'));
});

it('sends no raw content to a guest, who cannot reach the editor anyway', function () {
    $article = Article::factory()->create([
        'published' => true,
        'content' => contentTagging('entries.count', ['type' => 'note']),
    ]);

    get('/'.$article->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('entry.rawContent'));
});
