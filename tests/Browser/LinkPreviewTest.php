<?php

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Pest.php only binds TestCase/RefreshDatabase to tests/Feature, so a Browser
// test needs the same binding locally to get a real (migrated) database.
uses(TestCase::class, RefreshDatabase::class);

it('shows a preview card when hovering an internal content link', function () {
    // Target article the source links to. Aligning the seeded slug with the
    // /YYYY/MM/DD/slug permalink format is what makes BuildLinkPreviews (Task 1)
    // resolve the href to a TimelineEntry and return a preview.
    $target = Article::factory()->create([
        'title' => 'Target Post',
        'excerpt' => 'A short summary.',
        'occurred_at' => '2026-05-01 10:00:00',
        'slug' => 'target-post',
        'published' => true,
        'content' => [],
    ]);

    $source = Article::factory()->create([
        'occurred_at' => '2026-05-02 10:00:00',
        'slug' => 'source-post',
        'published' => true,
        'content' => [[
            '_type' => 'block',
            'markDefs' => [['_key' => 'a', '_type' => 'link', 'href' => '/2026/05/01/target-post']],
            'children' => [['_type' => 'span', 'marks' => ['a'], 'text' => 'see the target post']],
        ]],
    ]);

    $page = visit('/2026/05/02/source-post');

    $page->assertSee('see the target post');

    // Hovering the internal link should schedule the card open (~350ms delay
    // in LinkPreviewLayer); wait past that before asserting the teleported
    // card is visible with the target's title.
    $page->hover('a[href="/2026/05/01/target-post"]')
        ->wait(0.6)
        ->assertSee('Target Post');
});
