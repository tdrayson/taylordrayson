<?php

use App\Enums\CommentStatus;
use App\Enums\ReactionType;
use App\Models\Note;
use App\Support\PortableText;

/**
 * The heading is a total, so it has to be reachable by adding up the row.
 *
 * It used to count rows in the list below, while the reaction pile counted
 * on-site clicks plus webmention likes. The likes were in both, the on-site
 * clicks in neither, so no arithmetic a reader tried could work.
 */
it('adds the summary row up to the number in the heading', function () {
    $note = Note::factory()->create([
        'occurred_at' => now()->subHour(),
        'content' => PortableText::fromPlainText('Something worth responding to.'),
    ]);

    foreach ([ReactionType::Love, ReactionType::Haha, ReactionType::Wow] as $i => $type) {
        $note->reactions()->create(['type' => $type, 'identity_key' => hash('sha256', "sum-{$i}")]);
    }

    $note->comments()->create([
        'author_name' => 'Marty Spargo',
        'body' => PortableText::fromPlainText('Good one.'),
        'status' => CommentStatus::Approved,
    ]);

    // One of every kind, so nothing is counted twice and nothing is missed.
    foreach (['reply', 'like', 'repost', 'bookmark', 'rsvp', 'mention', 'reacji'] as $i => $kind) {
        $note->webmentions()->create([
            'source_url' => "https://jan.example/{$kind}",
            'target_url' => config('app.url').$note->url(),
            'kind' => $kind,
            'author_name' => 'Jan',
            'content' => $kind === 'reacji' ? PortableText::fromPlainText('🎉') : null,
            'status' => CommentStatus::Approved,
            'verified_at' => now(),
            'published_at' => now()->subMinutes($i),
        ]);
    }

    // 3 on-site + like + reacji = 5 reactions, 1 comment + 1 reply = 2 replies,
    // and one each of repost, bookmark, RSVP and mention. Eleven in total.
    $page = visit($note->url())->assertPresent('[data-testid="reaction-bar"]');

    $page->assertScript("document.querySelector('#responses').textContent.trim()", '11 interactions');

    // The pile's per-disc counts are a breakdown of the reaction total, not
    // figures of their own, so they are not part of the sum.
    $page->assertScript(
        "[...document.querySelectorAll('[data-testid=\"reaction-bar\"] .tnum')]"
            .".filter((el) => ! el.closest('.reaction-item'))"
            .'.reduce((sum, el) => sum + Number(el.textContent), 0)',
        11,
    );
});
