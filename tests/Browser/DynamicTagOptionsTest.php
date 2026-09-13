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

it('resolves typed free text to a date and applies it as a lone from bound', function () {
    Note::factory()->create(['occurred_at' => '2019-06-01 12:00:00']);
    Note::factory()->create(['occurred_at' => '2023-06-01 12:00:00']);

    $page = visit('/new/note');

    pickEntriesCountTag($page);

    $page->select('#dt-period', '__range__');
    $page->click('[aria-label="From date"]');
    $page->fill('[aria-label="From date, typed"]', '1 january 2020');
    $page->keys('[aria-label="From date, typed"]', 'Enter');
    $page->wait(1);

    // Resolved through the server into an absolute date, not left as typed text.
    $page->assertScript("document.querySelector('[aria-label=\"From date\"]').innerText.trim()", '1 Jan 2020');

    $page->click('button:has-text("Apply")');

    // Only the 2023 note is on or after the resolved bound; `to` was never touched.
    $page->assertScript("document.querySelector('.prose-editor [aria-label^=\"Edit \"]').innerText.trim()", '1');
});

it('reopens a lone from bound with no to still filled in', function () {
    Note::factory()->create(['occurred_at' => '2019-06-01 12:00:00']);
    Note::factory()->create(['occurred_at' => '2023-06-01 12:00:00']);

    $page = visit('/new/note');

    pickEntriesCountTag($page);

    $page->select('#dt-period', '__range__');
    $page->click('[aria-label="From date"]');
    $page->fill('[aria-label="From date, typed"]', '1 january 2020');
    $page->keys('[aria-label="From date, typed"]', 'Enter');
    $page->wait(1);
    $page->click('button:has-text("Apply")');

    $page->click('.prose-editor [aria-label^="Edit "]');

    $page->assertScript("document.querySelector('#dt-period').value", '__range__')
        ->assertScript("document.querySelector('[aria-label=\"From date\"]').innerText.trim()", '1 Jan 2020')
        ->assertScript("document.querySelector('[aria-label=\"To date\"]').innerText.trim()", 'To');
});

it('shows the preview icon only once ticked, the same glyph the published chip would show', function () {
    Note::factory()->create(['occurred_at' => '2019-06-01 12:00:00']);

    $page = visit('/new/note');

    pickEntriesCountTag($page);

    $page->select('#dt-type', 'note');

    // No icon by default: the option is off, and there is never a chart icon.
    $page->assertScript("document.querySelector('[role=\"dialog\"] .bg-neutral-25 svg') === null", true);

    $page->click('label:has-text("Include icon")');
    $page->wait(1);

    $page->assertScript("document.querySelector('[role=\"dialog\"] .bg-neutral-25 svg') !== null", true);
});

it('rejects an empty custom range until at least one bound is set', function () {
    $page = visit('/new/note');

    pickEntriesCountTag($page);

    $page->select('#dt-period', '__range__');

    $page->assertScript("document.querySelector('button[type=\"submit\"]').disabled", true);

    $page->click('[aria-label="From date"]');
    $page->fill('[aria-label="From date, typed"]', 'yesterday');
    $page->keys('[aria-label="From date, typed"]', 'Enter');
    $page->wait(1);

    $page->assertScript("document.querySelector('button[type=\"submit\"]').disabled", false);
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
