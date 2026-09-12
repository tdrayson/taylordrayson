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
    $note = Note::factory()->create(['timezone' => 'Europe/London']);

    $comment = $note->comments()->create([
        'author_name' => 'Jo',
        'body' => PortableText::fromPlainText('Hello from New York.'),
        'status' => CommentStatus::Approved,
        'timezone' => 'America/New_York',
    ]);

    // Pinned on the row rather than via Carbon::setTestNow(): the page is
    // rendered by a separate server process that never sees the test
    // process's fake clock, so an unpinned created_at would take the real
    // date and drift the expected offset once daylight saving ends.
    $comment->forceFill(['created_at' => Carbon::parse('2026-09-12 16:16:00')])->saveQuietly();

    visit($note->url())
        ->assertScript("document.querySelector('.h-cite .dt-published').textContent.includes('-04:00')", true)
        ->assertNoJavascriptErrors();
});
