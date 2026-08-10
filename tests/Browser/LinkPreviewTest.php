<?php

use App\Models\Article;

// No local uses() here: Pest.php binds TestCase and RefreshDatabase to Browser
// as well as Feature. Rebinding TestCase locally made the whole directory
// unloadable ("already uses the test case"), so `test tests/Browser` aborted
// before running anything and the browser tests could only be run file by file.

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

    // Asserting absence first is what catches a binding that never fires:
    // assertSee() alone passes on the title in the Inertia props JSON. The class
    // qualifier is needed too, since a bare tag name is read as a text guess.
    $page->assertNotPresent('article.rounded-lg');

    // Hovering the internal link should schedule the card open (~350ms delay
    // in LinkPreviewLayer); wait past that before asserting the teleported
    // card is visible with the target's title.
    $page->hover('a[href="/2026/05/01/target-post"]')
        ->wait(0.6)
        ->assertVisible('article.rounded-lg')
        ->assertSeeIn('article.rounded-lg', 'Target Post');
});
