<?php

use App\Enums\CommentStatus;
use App\Models\Note;
use App\Support\PortableText;

/**
 * A response read out of somebody else's thread has to look like what it is: an
 * answer to the mention above it, not another response to the entry.
 *
 * Asserted on the rendered DOM, because the indent and the property class both
 * exist only once Vue has drawn the thread.
 */
function noteWithNestedMention(): Note
{
    $note = Note::factory()->create([
        'occurred_at' => now()->subDay(),
        'content' => PortableText::fromPlainText('Something worth answering.'),
    ]);

    $note->webmentions()->create([
        'source_url' => 'https://jo.example/their-post',
        'target_url' => config('app.url').$note->url(),
        'kind' => 'reply',
        'author_name' => 'Jo Bloggs',
        'author_url' => 'https://jo.example/',
        'content' => PortableText::fromPlainText('Good point, and here is mine.'),
        'status' => CommentStatus::Approved,
        'verified_at' => now(),
        'published_at' => now()->subHours(2),
    ]);

    $note->webmentions()->create([
        'source_url' => 'https://chris.example/1',
        'parent_source_url' => 'https://jo.example/their-post',
        'target_url' => config('app.url').$note->url(),
        'kind' => 'reply',
        'author_name' => 'Chris',
        'author_url' => 'https://chris.example/',
        'content' => PortableText::fromPlainText('Agreed with Jo.'),
        'status' => CommentStatus::Approved,
        'verified_at' => now(),
        'published_at' => now()->subHour(),
    ]);

    return $note;
}

it('hangs a nested response off the mention that carried it', function () {
    $note = noteWithNestedMention();

    visit($note->url())
        ->assertPresent('#mention-2.response-nested')
        // The one that arrived directly stays on the rail rather than joining
        // the branch: only what was read out of their thread is indented.
        ->assertScript("document.querySelector('#mention-1').classList.contains('response-nested') === false")
        ->assertNoJavascriptErrors();
});

// Still a p-comment h-cite: a parser upstream reads the whole thread out of our
// page, so indenting it must not cost it its property.
it('keeps a nested response readable as a comment on this entry', function () {
    $note = noteWithNestedMention();

    visit($note->url())->assertPresent('#mention-2.p-comment.h-cite');
});
