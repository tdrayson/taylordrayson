<?php

use App\Models\Page;
use App\Models\User;

/**
 * A paragraph whose spans carry the spaces either side of a link, which is how
 * the editor sends prose with a link in the middle of a sentence.
 *
 * @return array<int, mixed>
 */
function paragraphAroundLink(): array
{
    return [[
        '_type' => 'block',
        '_key' => 'b1',
        'style' => 'normal',
        'markDefs' => [['_key' => 'l1', '_type' => 'link', 'href' => '/about']],
        'children' => [
            ['_type' => 'span', '_key' => 's1', 'text' => 'your finalised ', 'marks' => []],
            ['_type' => 'span', '_key' => 's2', 'text' => 'copy', 'marks' => ['l1']],
            ['_type' => 'span', '_key' => 's3', 'text' => ' is not ready.', 'marks' => []],
        ],
    ]];
}

it('keeps the spaces either side of a link when a page is saved', function () {
    $page = Page::factory()->create(['slug' => 'about-us', 'title' => 'About us', 'published' => true]);

    $this->actingAs(User::factory()->create())
        ->patch("/entries/page/{$page->id}", [
            'title' => $page->title,
            'slug' => $page->slug,
            'content' => paragraphAroundLink(),
            'published' => true,
        ])
        ->assertRedirect();

    // Laravel's TrimStrings walks nested arrays, so without an exception these
    // come back as 'your finalised' and 'is not ready.' and the words collide.
    expect(array_column($page->fresh()->content[0]['children'], 'text'))
        ->toBe(['your finalised ', 'copy', ' is not ready.']);
});
