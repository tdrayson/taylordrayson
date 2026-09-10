<?php

use App\Models\Note;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

/** Types "{count" and picks the only match, `entries.count`. */
function pickEntriesCountTag($page): void
{
    $page->click('.prose-editor')->typeSlowly('.prose-editor', '{count');
    $page->keys('.prose-editor', ['Enter']);
}

it('opens the options popup instead of inserting bare, and cancels clean on escape', function () {
    $page = visit('/new/note');

    pickEntriesCountTag($page);

    $page->assertScript("!!document.querySelector('[role=\"dialog\"]')", true)
        ->assertSee('Entry count');

    $page->keys('[role="dialog"]', 'Escape');

    $page->assertScript("document.querySelector('[role=\"dialog\"]') === null", true)
        // Nothing inserted, and the typed trigger is gone.
        ->assertScript("document.querySelectorAll('.prose-editor [aria-label^=\"Edit \"]').length", 0)
        ->assertScript("document.querySelector('.prose-editor').innerText.includes('{count')", false);
});

it('applies a chosen period and shows the resolved count, not the all-time default', function () {
    Note::factory()->create(['occurred_at' => '2019-06-01 12:00:00']);
    Note::factory()->create(['occurred_at' => '2023-06-01 12:00:00']);

    $page = visit('/new/note');

    pickEntriesCountTag($page);

    $page->select('#dt-period', '__year__');
    $page->fill('[aria-label="Year"]', '2019');
    $page->click('button:has-text("Apply")');

    $page->assertScript("document.querySelector('.prose-editor [aria-label^=\"Edit \"]').innerText.trim()", '1');
});

it('reopens an existing chip with its current options and updates the chip on change', function () {
    Note::factory()->create(['occurred_at' => '2019-06-01 12:00:00']);
    Note::factory()->create(['occurred_at' => '2023-06-01 12:00:00']);

    $page = visit('/new/note');

    pickEntriesCountTag($page);
    $page->click('button:has-text("Apply")');

    // Default period (all-time): both notes count.
    $page->assertScript("document.querySelector('.prose-editor [aria-label^=\"Edit \"]').innerText.trim()", '2');

    $page->click('.prose-editor [aria-label^="Edit "]');
    $page->assertScript("!!document.querySelector('[role=\"dialog\"]')", true)
        ->assertScript("document.querySelector('#dt-period').value", 'all-time');

    $page->select('#dt-period', '__year__');
    $page->fill('[aria-label="Year"]', '2023');
    $page->click('button:has-text("Apply")');

    $page->assertScript("document.querySelector('.prose-editor [aria-label^=\"Edit \"]').innerText.trim()", '1');
});

it('rejects a malformed custom year until it is exactly four digits', function () {
    $page = visit('/new/note');

    pickEntriesCountTag($page);

    $page->select('#dt-period', '__year__');
    $page->fill('[aria-label="Year"]', '99');

    $page->assertScript("document.querySelector('button[type=\"submit\"]').disabled", true);

    $page->fill('[aria-label="Year"]', '2019');
    $page->assertScript("document.querySelector('button[type=\"submit\"]').disabled", false);
});
