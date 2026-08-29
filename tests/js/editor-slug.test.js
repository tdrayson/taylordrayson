import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { noteSlug, slugify, slugifyInput, tagName } from '../../resources/js/lib/editor/defaults.js';

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
