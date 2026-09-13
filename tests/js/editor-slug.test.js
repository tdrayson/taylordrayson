import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { noteSlug, responseSlug, slugify, slugifyInput, tagName } from '../../resources/js/lib/editor/defaults.js';

/** A Portable Text document holding one paragraph. */
const doc = (text) => [{ _type: 'block', children: [{ _type: 'span', text }] }];

describe('slugify', () => {
    it('drops punctuation rather than making a separator of it', () => {
        // Str::slug's behaviour: taylors-day, not taylor-s-day.
        assert.equal(slugify("Taylor's day"), 'taylors-day');
        assert.equal(slugify('Hello, world!'), 'hello-world');
    });

    it('folds accents and collapses runs', () => {
        assert.equal(slugify('Café  crème'), 'cafe-creme');
        assert.equal(slugify('  spaced  out  '), 'spaced-out');
    });
});

describe('slugifyInput', () => {
    it('keeps a trailing separator so a typed space survives', () => {
        // Trimming here would delete the separator between each word as it is
        // typed, and the next character would join the previous word.
        assert.equal(slugifyInput('hello '), 'hello-');
        assert.equal(slugifyInput('hello w'), 'hello-w');
    });

    it('still refuses a leading separator and any space', () => {
        assert.equal(slugifyInput(' leading'), 'leading');
        assert.equal(slugifyInput('two words here'), 'two-words-here');
    });
});

describe('noteSlug', () => {
    // These expectations are mirrored in NoteSlugTest against Note::slugFrom;
    // the two implementations have to agree for the editor's preview to be true.
    it('uses the note\'s opening words', () => {
        assert.equal(
            noteSlug(doc("Today wasn't a great day. Everything went wrong at once.")),
            'today-wasnt-a-great-day-everything',
        );
    });

    it('takes a short note whole', () => {
        assert.equal(noteSlug(doc('Hello')), 'hello');
    });

    it('falls back when there are no words to use', () => {
        assert.equal(noteSlug(doc('👍')), 'note');
        assert.equal(noteSlug([]), 'note');
        assert.equal(noteSlug(null), 'note');
    });

    it('reads across blocks', () => {
        assert.equal(noteSlug([...doc('One two'), ...doc('three four')]), 'one-two-three-four');
    });
});

describe('tagName', () => {
    it('capitalises a lowercase tag but leaves deliberate casing alone', () => {
        assert.equal(tagName('living alone'), 'Living Alone');
        assert.equal(tagName('sci-fi'), 'Sci-Fi');
        assert.equal(tagName('TV show'), 'TV Show');
        assert.equal(tagName('iOS'), 'iOS');
    });
});

describe('responseSlug', () => {
    // Mirrored in ResponseSlugTest against NameResponseSlug.
    const url = 'https://www.example.com/post';
    const cited = (title, authorName = null) => ({ url, internal: false, cited: { title, authorName } });

    it('is null for a plain note or an rsvp with no answer', () => {
        assert.equal(responseSlug({ kind: '', url }), null);
        assert.equal(responseSlug({ kind: 'like', url: '' }), null);
        assert.equal(responseSlug({ kind: 'rsvp', url, rsvp: '' }), null);
    });

    it('names a like or repost after the domain, whatever was fetched', () => {
        assert.equal(responseSlug({ kind: 'like', url, preview: cited('A Title') }), 'like-example-com');
        assert.equal(responseSlug({ kind: 'repost', url: 'https://aaronparecki.com/a' }), 'repost-aaronparecki-com');
    });

    it('names a reply after the title, then the author, then the domain', () => {
        assert.equal(responseSlug({ kind: 'reply', url, preview: cited('Sending your First Webmention', 'Aaron') }), 'reply-to-sending-your-first-webmention');
        assert.equal(responseSlug({ kind: 'reply', url, preview: cited(null, 'Aaron Parecki') }), 'reply-to-aaron-parecki');
        assert.equal(responseSlug({ kind: 'reply', url }), 'reply-to-example-com');
    });

    it('cuts a long title to the words a note slug uses', () => {
        assert.equal(responseSlug({ kind: 'reply', url, preview: cited('One two three four five six seven eight') }), 'reply-to-one-two-three-four-five-six');
    });

    it('names an rsvp after the title, never the author', () => {
        assert.equal(responseSlug({ kind: 'rsvp', url, rsvp: 'yes', preview: cited('IndieWebCamp Brighton', 'Somebody') }), 'rsvp-indiewebcamp-brighton');
        assert.equal(responseSlug({ kind: 'rsvp', url, rsvp: 'yes', preview: cited(null, 'Somebody') }), 'rsvp-example-com');
    });

    it('names one of my own entries by the name the preview gives it', () => {
        const preview = { url, internal: true, title: 'My Great Article', cited: null };

        assert.equal(responseSlug({ kind: 'like', url, preview }), 'like-my-great-article');
    });

    it('ignores a preview fetched for a different url', () => {
        assert.equal(responseSlug({ kind: 'reply', url, preview: { ...cited('Old'), url: 'https://example.com/old' } }), 'reply-to-example-com');
    });
});
