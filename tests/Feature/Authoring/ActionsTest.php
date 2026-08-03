<?php

use App\Actions\Articles\CreateArticle;
use App\Actions\Articles\UpdateArticle;
use App\Actions\Pages\CreatePage;
use App\Actions\Pages\UpdatePage;
use App\Actions\Projects\CreateProject;
use App\Models\Article;
use App\Models\Page;
use App\Models\Project;

it('creates a page unpublished, so a half-written /about never goes live by accident', function () {
    $page = app(CreatePage::class)(['title' => 'About']);

    expect($page->published)->toBeFalse()
        ->and($page->slug)->toBe('about');

    // Visible to nobody until published.
    $this->get('/about')->assertNotFound();
});

it('derives a page slug from the title but keeps an explicit one', function () {
    expect(app(CreatePage::class)(['title' => 'Colophon and Credits'])->slug)->toBe('colophon-and-credits')
        ->and(app(CreatePage::class)(['title' => 'Uses', 'slug' => 'kit'])->slug)->toBe('kit');
});

it('publishes a page, which is what puts it on its URL', function () {
    $page = app(CreatePage::class)(['title' => 'About']);

    app(UpdatePage::class)($page, ['published' => true]);

    $this->get('/about')->assertOk();
});

it('keeps a draft article off the timeline until it is published', function () {
    $article = app(CreateArticle::class)([
        'title' => 'How OG images work',
        'content' => [['_type' => 'block', 'children' => [['text' => 'Draft.']]]],
    ]);

    expect($article->published)->toBeFalse()
        ->and($article->timelineEntry)->toBeNull();

    app(UpdateArticle::class)($article, ['published' => true]);

    expect($article->fresh()->timelineEntry)->not->toBeNull();
});

it('syncs tags when creating an article', function () {
    $article = app(CreateArticle::class)([
        'title' => 'Tagged piece',
        'tags' => ['Writing', 'Meta'],
    ]);

    expect($article->tags->pluck('slug')->all())->toEqualCanonicalizing(['writing', 'meta']);
});

it('replaces tags on update rather than appending', function () {
    $article = app(CreateArticle::class)(['title' => 'Retagged', 'tags' => ['One', 'Two']]);

    app(UpdateArticle::class)($article, ['tags' => ['Three']]);

    expect($article->fresh()->tags->pluck('slug')->all())->toBe(['three']);
});

it('creates a project as active, since projects have no draft state', function () {
    $project = app(CreateProject::class)(['title' => 'Taylor Drayson dot com']);

    expect($project->status)->toBe('active')
        ->and($project->slug)->toBe('taylor-drayson-dot-com')
        // No publish gate, so it is on the timeline immediately.
        ->and($project->timelineEntry)->not->toBeNull();
});

it('leaves the models it does not own alone', function () {
    app(CreatePage::class)(['title' => 'About']);

    expect(Page::count())->toBe(1)
        ->and(Article::count())->toBe(0)
        ->and(Project::count())->toBe(0);
});
