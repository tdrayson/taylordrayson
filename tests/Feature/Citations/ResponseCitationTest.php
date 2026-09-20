<?php

use App\Enums\ResponseKind;
use App\Models\Citation;
use App\Models\Note;

function replyTo(Citation $citation, array $attributes = []): Note
{
    return Note::factory()->create(array_merge([
        'response_kind' => ResponseKind::Reply,
        'response_url' => $citation->url,
    ], $attributes))->fresh();
}

it('names the post from its citation in the byline', function () {
    $citation = Citation::factory()->create(['url' => 'https://example.com/post', 'title' => 'Sending your First Webmention']);

    $this->get(replyTo($citation)->url())
        ->assertInertia(fn ($page) => $page->where('entry.response.title', 'Sending your First Webmention'));
});

it('carries the author, quote and date for the entry page', function () {
    $citation = Citation::factory()->create([
        'url' => 'https://example.com/post',
        'author_name' => 'Aaron Parecki',
        'author_photo_path' => 'avatars/aaron.webp',
        'excerpt' => 'What Aaron wrote.',
        'published_at' => '2018-07-01 03:35:00',
        'published_timezone' => '-07:00',
    ]);

    $this->get(replyTo($citation)->url())
        ->assertInertia(fn ($page) => $page
            ->where('entry.response.cited.authorName', 'Aaron Parecki')
            ->where('entry.response.cited.authorPhoto', '/avatars/aaron.webp')
            ->where('entry.response.cited.quote', 'What Aaron wrote.')
            ->where('entry.response.cited.published.offset', '-07:00'));
});

it('prefers the trimmed quote over the fetched excerpt', function () {
    $citation = Citation::factory()->create(['url' => 'https://example.com/post', 'excerpt' => 'The whole opening.']);

    $this->get(replyTo($citation, ['response_quote' => 'Just this bit.'])->url())
        ->assertInertia(fn ($page) => $page->where('entry.response.cited.quote', 'Just this bit.'));
});

it('falls back to calling it a post when nothing was stored', function () {
    $note = Note::factory()->create(['response_kind' => ResponseKind::Reply, 'response_url' => 'https://example.com/unfetched']);

    $this->get($note->url())
        ->assertInertia(fn ($page) => $page
            ->where('entry.response.title', 'a post')
            ->where('entry.response.cited', null));
});
