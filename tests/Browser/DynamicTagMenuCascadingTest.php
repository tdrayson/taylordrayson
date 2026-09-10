<?php

use App\Models\State;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('browses the cascading menu by category before a query narrows it to a flat list', function () {
    $page = visit('/new/note');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '{');

    // The unfiltered menu is cascading: only the active category's tags are
    // options, so the count is well short of all 36 inline-capable tags.
    $page->assertScript(
        "document.querySelectorAll('[role=\"option\"]').length > 0 && document.querySelectorAll('[role=\"option\"]').length < 10",
        true,
    );

    $page->typeSlowly('.prose-editor', 'up');

    // Typing switches to the flat filtered list, which spans every category
    // whose tags match "up" ("updated" in four ambient groups, plus site.updated).
    $page->assertScript("document.querySelectorAll('[role=\"option\"]').length", 5);
});

it('picks a tag from a category reached with the arrow keys alone', function () {
    State::query()->create([
        'key' => 'now.battery',
        'value' => json_encode(['percent' => 66, 'charging' => false, 'low_power' => false]),
        'observed_at' => now(),
    ]);

    $page = visit('/new/note');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '{');

    // Entries, Streaks, Site, then Battery: three rights lands on it, and its
    // first field is "Percent", which takes no options.
    $page->keys('.prose-editor', ['ArrowRight', 'ArrowRight', 'ArrowRight', 'Enter']);

    $page->assertScript(
        "document.querySelector('.prose-editor [aria-label=\"Dynamic tag: ambient.battery.percent\"]').innerText.trim()",
        '66',
    );
});
