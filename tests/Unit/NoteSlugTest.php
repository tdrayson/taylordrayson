<?php

use App\Models\Note;
use App\Support\PortableText;

/*
 * These expectations are mirrored in tests/js/editor-slug.test.js against
 * noteSlug(). The editor previews the URL a note will get before it is saved,
 * so the two implementations have to produce the same string.
 */

it('derives a slug from the note\'s opening words', function () {
    expect(Note::slugFrom(PortableText::fromPlainText("Today wasn't a great day. Everything went wrong at once.")))
        ->toBe('today-wasnt-a-great-day-everything');
});

it('takes a short note whole', function () {
    expect(Note::slugFrom(PortableText::fromPlainText('Hello')))->toBe('hello');
});

it('falls back when there are no words to use', function () {
    expect(Note::slugFrom(PortableText::fromPlainText('👍')))->toBe('note')
        ->and(Note::slugFrom(null))->toBe('note');
});

it('reads across blocks', function () {
    $document = [
        ...PortableText::fromPlainText('One two'),
        ...PortableText::fromPlainText('three four'),
    ];

    expect(Note::slugFrom($document))->toBe('one-two-three-four');
});
