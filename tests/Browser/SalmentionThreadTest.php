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

// A reply to their post, published inside their citation rather than beside it.
// Flattened onto the entry it would read as a direct reply to me, which is the
// one thing the thread shape is there to say it is not.
it('publishes a nested response inside the citation that carried it', function () {
    $note = noteWithNestedMention();

    visit($note->url())
        ->assertPresent('#mention-2.p-comment.h-cite')
        ->assertScript("document.querySelector('#mention-2').parentElement.closest('.h-cite').id === 'mention-1'");
});

// The class names are not the contract: what a consumer reads is the parsed
// tree, and whether a property lands on the entry or on the citation is a
// question about scoping that a selector cannot answer.
it('parses as a comment of the mention, not as a comment of the entry', function () {
    $note = noteWithNestedMention();

    $page = visit($note->url())->assertPresent('#mention-2');

    $parsed = parseMicroformats(
        $page->script('document.documentElement.outerHTML'),
        config('app.url').$note->url(),
    );

    $entry = microformatItem($parsed, 'h-entry');
    $citation = $entry['properties']['comment'][0];

    expect($entry['properties']['comment'])->toHaveCount(1)
        ->and($citation['properties']['author'][0]['properties']['name'][0])->toBe('Jo Bloggs')
        ->and($citation['properties']['comment'][0]['properties']['author'][0]['properties']['name'][0])->toBe('Chris');
});
