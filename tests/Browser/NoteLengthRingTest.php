<?php

use App\Models\Note;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

// The ring counts client-side and the save is refused server-side, so the two
// have to measure the same thing. Bolding the middle of a word splits one span
// into three with no whitespace at the seams, which is exactly where a join
// that puts spaces between spans invents characters nobody typed.
it('counts what you can see, not the spans it is stored in', function () {
    $browser = visit('/new/note');

    $browser->click('.prose-editor')->typeSlowly('.prose-editor', 'That was unbelievable', 20);

    // Select "believ" and bold it through the toolbar, the way you would.
    // assertScript evaluates an expression, so the selection runs as an IIFE.
    $browser->assertScript("(() => {
        const paragraph = document.querySelector('.prose-editor p');
        const range = document.createRange();
        range.setStart(paragraph.firstChild, 11);
        range.setEnd(paragraph.firstChild, 17);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        paragraph.dispatchEvent(new Event('mouseup', { bubbles: true }));

        return true;
    })()", true);

    $browser->click('[aria-label="Bold"]');

    // Three spans now, and the word still reads as one.
    $browser->assertScript("document.querySelector('.prose-editor').querySelectorAll('strong').length === 1", true);
    $browser->assertScript("document.querySelector('.prose-editor').innerText.trim() === 'That was unbelievable'", true);

    $browser->assertScript("
        document.querySelector('[aria-label\$=\"characters\"]').getAttribute('aria-label') === '21 of 750 characters'
    ", true);
});

// The nudge and the count are the two things that only appear near the cap, so
// the thing worth proving is that they stay away until then: a note that is
// nowhere near 750 should say nothing at all about its length.
it('stays quiet until the cap is in sight, then counts', function () {
    $short = visit('/new/note');

    $short->click('.prose-editor')->type('.prose-editor', str_repeat('word ', 40));

    // "/ 750" rather than "750", which also sits in the field definition Inertia
    // ships in the page props.
    $short->assertDontSee('/ 750')->assertDontSee('This is getting long');

    $near = visit('/new/note');

    $near->click('.prose-editor')->type('.prose-editor', str_repeat('word ', 143).'wor');

    $near->assertSee('718 / 750')->assertSee('This is getting long for a note');
});

it('offers to convert a note that outgrew the limit, carrying the words across', function () {
    $browser = visit('/new/note');

    $browser->click('.prose-editor')->type('.prose-editor', str_repeat('word ', Note::MAX_LENGTH / 4));

    $browser->assertSee('Too long for a note');

    // The limit is soft on the way in and hard on the way out: nothing typed is
    // thrown away, but the save the server would refuse never leaves.
    $browser->assertScript(
        "[...document.querySelectorAll('button')].find((one) => one.textContent.trim() === 'Post').disabled",
        true,
    );

    $browser->click('button:has-text("Turn it into an article")');

    $browser->assertScript("location.pathname === '/new/article'", true);

    // The words crossed over rather than being retyped, and nothing was saved
    // on the way: a note has no draft state to convert from.
    $browser->assertScript("document.querySelector('.prose-editor').innerText.startsWith('word word')", true);

    expect(Note::count())->toBe(0);
});
