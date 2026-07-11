<?php

use App\Models\Article;

use function Pest\Laravel\get;

it('exposes previews only for internal, previewable content links', function () {
    // Target article the link points to. Content is left empty so the card's
    // subtitle falls back to the explicit excerpt (PortableText::plainText of
    // a non-empty body would otherwise win).
    $target = Article::factory()->create([
        'title' => 'Target Post',
        'excerpt' => 'A short summary.',
        'occurred_at' => '2026-05-01 10:00:00',
        'slug' => 'target-post',
        'published' => true,
        'content' => [],
    ]);
    $targetHref = '/2026/05/01/target-post';

    // Source article whose content links to the target, plus an external link
    // and an internal link to a non-previewable path.
    $source = Article::factory()->create([
        'occurred_at' => '2026-05-02 10:00:00',
        'slug' => 'source-post',
        'published' => true,
        'content' => [[
            '_type' => 'block',
            'markDefs' => [
                ['_key' => 'a', '_type' => 'link', 'href' => $targetHref],
                ['_key' => 'b', '_type' => 'link', 'href' => 'https://example.com'],
                ['_key' => 'c', '_type' => 'link', 'href' => '/photos'],
            ],
            'children' => [
                ['_type' => 'span', 'marks' => ['a'], 'text' => 'target'],
                ['_type' => 'span', 'marks' => ['b'], 'text' => 'external'],
                ['_type' => 'span', 'marks' => ['c'], 'text' => 'photos'],
            ],
        ]],
    ]);

    get('/2026/05/02/source-post')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('linkPreviews', 1)
            ->where("linkPreviews.$targetHref.title", 'Target Post')
            ->where("linkPreviews.$targetHref.excerpt", 'A short summary.')
            ->where("linkPreviews.$targetHref.type", 'article')
            ->where("linkPreviews.$targetHref.url", $targetHref)
        );
});
