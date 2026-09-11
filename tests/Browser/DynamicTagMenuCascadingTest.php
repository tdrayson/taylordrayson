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

it('nests a ring value under its own goal and percent as a third tier', function () {
    State::query()->create([
        'key' => 'now.rings',
        'value' => json_encode(['move' => 118, 'move_goal' => 250, 'exercise' => 22, 'exercise_goal' => 30, 'stand' => 9, 'stand_goal' => 12, 'steps' => 4213]),
        'observed_at' => now(),
    ]);

    $page = visit('/new/note');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '{');

    // Entries, Streaks, Site, Battery, Weather, Location, then Rings.
    $page->keys('.prose-editor', ['ArrowRight', 'ArrowRight', 'ArrowRight', 'ArrowRight', 'ArrowRight', 'ArrowRight']);

    // Move/Exercise/Stand each carry their own goal and percent underneath
    // them, in that order, ahead of the two fields with no subgroup.
    $page->assertScript(
        "Array.from(document.querySelectorAll('[role=\"option\"]')).map((el) => el.innerText.split('\\n')[0]).join('|')",
        'Move|Goal|Percent|Exercise|Goal|Percent|Stand|Goal|Percent|Steps|Updated',
    );

    // A child's visible "Goal" is ambiguous on its own; the full label survives as its accessible name.
    $page->assertScript(
        "document.querySelectorAll('[role=\"option\"]')[1].getAttribute('aria-label')",
        'Move goal',
    );
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
