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

it('opens a real third pane for each ring instead of indenting its goal and percent', function () {
    State::query()->create([
        'key' => 'now.rings',
        'value' => json_encode(['move' => 118, 'move_goal' => 250, 'exercise' => 22, 'exercise_goal' => 30, 'stand' => 9, 'stand_goal' => 12, 'steps' => 4213]),
        'observed_at' => now(),
    ]);

    $page = visit('/new/note');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '{');

    // Entries, Streaks, Site, Battery, Weather, Location, then Rings.
    $page->keys('.prose-editor', ['ArrowRight', 'ArrowRight', 'ArrowRight', 'ArrowRight', 'ArrowRight', 'ArrowRight']);

    // Move/Exercise/Stand are parent rows in the second pane, unselectable,
    // so only Steps and Updated claim "option" there; landing on Move (the
    // default first row) already opened its own third pane alongside them.
    $page->assertScript(
        "Array.from(document.querySelectorAll('[role=\"option\"]')).map((el) => el.innerText.split('\\n')[0]).join('|')",
        'Steps|Updated|Move|Goal|Percent',
    );

    // Arrowing onto Exercise swaps the third pane to Exercise's own values,
    // proving it belongs to the active parent rather than being one flat list.
    $page->keys('.prose-editor', ['ArrowDown']);

    $page->assertScript(
        "Array.from(document.querySelectorAll('[role=\"option\"]')).map((el) => el.innerText.split('\\n')[0]).join('|')",
        'Steps|Updated|Exercise|Goal|Percent',
    );

    // A child's visible "Goal" is ambiguous on its own; the full label survives as its accessible name.
    $page->assertScript(
        "document.querySelector('[role=\"option\"][aria-label=\"Exercise goal\"]').innerText.split('\\n')[0]",
        'Goal',
    );
});

it('picks a ring value from its third pane with the keyboard', function () {
    State::query()->create([
        'key' => 'now.rings',
        'value' => json_encode(['move' => 118, 'move_goal' => 250, 'exercise' => 22, 'exercise_goal' => 30, 'stand' => 9, 'stand_goal' => 12, 'steps' => 4213]),
        'observed_at' => now(),
    ]);

    $page = visit('/new/note');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '{');

    // Entries, Streaks, Site, Battery, Weather, Location, then Rings, landing
    // on Move; right drills into its third pane, Enter picks the first leaf.
    $page->keys('.prose-editor', ['ArrowRight', 'ArrowRight', 'ArrowRight', 'ArrowRight', 'ArrowRight', 'ArrowRight', 'ArrowRight', 'Enter']);

    $page->assertScript(
        "document.querySelector('.prose-editor [aria-label=\"Dynamic tag: ambient.rings.move\"]').innerText.trim()",
        '118 kcal',
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

    // Entries, Streaks, Site, then Battery: three rights lands on it, one down
    // reaches "Charging", which takes no options (unlike "Percent", which now
    // offers an opt-in icon and would open the options popup instead).
    $page->keys('.prose-editor', ['ArrowRight', 'ArrowRight', 'ArrowRight', 'ArrowDown', 'Enter']);

    $page->assertScript(
        "document.querySelector('.prose-editor [aria-label=\"Dynamic tag: ambient.battery.charging\"]').innerText.trim()",
        'No',
    );
});
