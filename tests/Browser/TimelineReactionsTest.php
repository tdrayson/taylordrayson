<?php

use App\Enums\CommentStatus;
use App\Enums\ReactionType;
use App\Models\Note;
use App\Support\PortableText;

/**
 * The reaction row on a timeline card.
 *
 * Deferred, so it is absent from the first response and arrives on a second
 * request. A test asserting against the first paint would pass with the feature
 * removed entirely.
 */
function noteOnTheTimeline(): Note
{
    $note = Note::factory()->create([
        'occurred_at' => now()->subDay()->setTime(9, 0),
        'content' => PortableText::fromPlainText('Something worth reacting to.'),
    ]);

    foreach ([ReactionType::Love, ReactionType::Haha] as $i => $type) {
        $note->reactions()->create(['type' => $type, 'identity_key' => hash('sha256', "feed-{$i}")]);
    }

    $note->comments()->create([
        'author_name' => 'Marty Spargo',
        'body' => PortableText::fromPlainText('Good one.'),
        'status' => CommentStatus::Approved,
    ]);

    return $note;
}

it('brings the reaction row to a timeline card once the deferred prop lands', function () {
    noteOnTheTimeline();

    visit('/')
        ->assertPresent('[data-testid="reaction-bar"]')
        // Two reactions chosen, so two discs, and one written response.
        ->assertScript('document.querySelectorAll(\'[data-testid="reaction-bar"] .reaction-pip\').length', 2);
});

it('does not spread the pile on a feed card, which is scrolled rather than read', function () {
    noteOnTheTimeline();

    // The spread is a reading gesture: fifty of them down a page fight the
    // scroll, so the compact variant opts out.
    visit('/')
        ->assertPresent('[data-testid="reaction-bar"]')
        ->assertScript('document.querySelectorAll(\'[data-testid="reaction-bar"] .reaction-pile.is-static\').length', 1);
});
