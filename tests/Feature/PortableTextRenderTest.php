<?php

use App\Models\Article;
use App\Models\Page;
use App\Support\PortableText;

use function Pest\Laravel\get;

it('serves the portable text array in entry.content for an article page', function () {
    $article = Article::factory()->create([
        'occurred_at' => '2026-03-15 09:00:00',
        'published' => true,
        'content' => [
            PortableText::block('Hello World', 'h2'),
            PortableText::block('A paragraph of body text.'),
        ],
    ]);

    $url = $article->occurred_at->format('Y/m/d').'/'.$article->slug();

    get("/{$url}")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Entry')
        ->where('entry.content.0._type', 'block')
    );
});

it('serves the portable text array in content for a published page', function () {
    $page = Page::factory()->create([
        'slug' => 'about',
        'published' => true,
        'content' => [
            PortableText::block('About', 'h2'),
            PortableText::block('Some page body.'),
        ],
    ]);

    get("/{$page->slug}")->assertOk()->assertInertia(fn ($inertia) => $inertia
        ->component('Page')
        ->where('content.0._type', 'block')
    );
});

it('serves 200 for an article entry page whose content has two h2 blocks (TOC eligible)', function () {
    $article = Article::factory()->create([
        'occurred_at' => '2026-03-15 09:00:00',
        'published' => true,
        'content' => [
            PortableText::block('First section', 'h2'),
            PortableText::block('A paragraph of body text.'),
            PortableText::block('Second section', 'h2'),
            PortableText::block('Another paragraph of body text.'),
        ],
    ]);

    $url = $article->occurred_at->format('Y/m/d').'/'.$article->slug();

    get("/{$url}")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Entry')
        ->where('entry.content.0._type', 'block')
        ->where('entry.content.2.style', 'h2')
    );
});

it('shows a timeline card excerpt matching the plain text of the article content', function () {
    $article = Article::factory()->create([
        'occurred_at' => '2026-03-15 09:00:00',
        'published' => true,
        'content' => [
            PortableText::block('Hello World', 'h2'),
            PortableText::block('A short paragraph.'),
        ],
    ]);

    $expected = PortableText::plainText($article->content);

    get('/')->assertInertia(fn ($page) => $page
        ->component('Timeline')
        ->where('groups.0.items.0.meta', $expected)
    );
});
