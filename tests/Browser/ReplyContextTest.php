<?php

use App\Enums\ResponseKind;
use App\Models\Citation;
use App\Models\Note;

function replyNote(?Citation $citation, string $url = 'https://example.com/post'): Note
{
    return Note::factory()->create([
        'occurred_at' => now()->subHour(),
        'response_kind' => ResponseKind::Reply,
        'response_url' => $citation?->url ?? $url,
    ]);
}

it('names the author outside and quotes their words behind a rule', function () {
    $note = replyNote(Citation::factory()->create([
        'url' => 'https://example.com/post',
        'title' => 'Sending your First Webmention',
        'author_name' => 'Aaron Parecki',
        'author_photo_path' => null,
        'excerpt' => 'What Aaron wrote.',
    ]));

    visit($note->url())
        ->assertPresent('.h-cite.u-in-reply-to .p-author')
        ->assertScript("document.querySelector('.h-cite .p-author').textContent.trim()", 'Aaron Parecki')
        ->assertScript("document.querySelector('.h-cite .p-name').textContent.trim()", 'Sending your First Webmention')
        ->assertScript("document.querySelector('.h-cite .p-content').textContent.trim()", 'What Aaron wrote.')
        ->assertNoJavascriptErrors();
});

it('shows no avatar at all when the author has no photo', function () {
    $note = replyNote(Citation::factory()->create(['url' => 'https://example.com/post', 'author_photo_path' => null]));

    visit($note->url())->assertMissing('.h-cite img');
});

it('calls it a post rather than naming the site twice when there is no author', function () {
    $note = replyNote(Citation::factory()->create(['url' => 'https://example.com/post', 'author_name' => null]));

    visit($note->url())
        ->assertMissing('.h-cite .p-author')
        ->assertScript("document.querySelector('.h-cite a.u-url').textContent.trim()", 'a post');
});

it('draws only the byline when there is nothing to quote', function () {
    $note = replyNote(Citation::factory()->create(['url' => 'https://example.com/post', 'title' => null, 'excerpt' => null]));

    visit($note->url())->assertMissing('.h-cite .border-l-2');
});

it('links the host as a u-url when an author is named but nothing else is', function () {
    $note = replyNote(Citation::factory()->create([
        'url' => 'https://example.com/post',
        'author_name' => 'Aaron Parecki',
        'title' => null,
        'excerpt' => null,
        'published_at' => null,
    ]));

    visit($note->url())
        ->assertPresent('.h-cite a.u-url[href="https://example.com/post"]')
        ->assertScript("document.querySelector('.h-cite a.u-url').textContent.trim()", 'example.com');
});
