<?php

use App\Enums\CommentStatus;
use App\Models\Note;
use App\Support\PortableText;
use Carbon\Carbon;

/**
 * A response's own offset must show up in the rendered page, not only in the
 * Inertia props: that JSON passes even when the template never prints it.
 */
it('shows a response\'s own offset in the rendered timestamp, not just the props', function () {
    Carbon::setTestNow('2026-09-12 16:16:00');

    $note = Note::factory()->create(['timezone' => 'Europe/London']);

    $note->comments()->create([
        'author_name' => 'Jo',
        'body' => PortableText::fromPlainText('Hello from New York.'),
        'status' => CommentStatus::Approved,
        'timezone' => 'America/New_York',
    ]);

    visit($note->url())
        ->assertScript("document.querySelector('.h-cite .dt-published').textContent.includes('-04:00')", true)
        ->assertNoJavascriptErrors();
});
