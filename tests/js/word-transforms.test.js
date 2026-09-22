import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { rewriteWords } from '../../resources/js/lib/textMode.js';
import { toEmoji, toNumeronym, toPigLatin, toPirate, toReversed } from '../../resources/js/lib/wordTransforms.js';

describe('rewriteWords', () => {
    it('transforms each word and keeps spacing, numbers and punctuation', () => {
        assert.equal(rewriteWords('Ran 5 miles, then coffee!', (word) => word.toUpperCase()), 'RAN 5 MILES, THEN COFFEE!');
    });
});

describe('toNumeronym', () => {
    it('shortens words of four letters or more and keeps the rest of the text', () => {
        assert.equal(rewriteWords('Accessibility, internationalisation & the web!', toNumeronym), 'A11y, i18n & the web!');
    });

    it('counts letters rather than code units and ignores apostrophes', () => {
        assert.equal(rewriteWords("don't café", toNumeronym), 'd2t c2é');
    });
});

describe('toPirate', () => {
    it('swaps known words and keeps their capitals', () => {
        assert.equal(rewriteWords('Hello friends, YES the coffee is great', toPirate), "Ahoy hearties, AYE th' grog be mighty");
    });

    it('drops the g from long -ing words and leaves the rest', () => {
        assert.equal(rewriteWords('running king', toPirate), "runnin' king");
    });

    it('reads curly apostrophes', () => {
        assert.equal(toPirate('It’s'), "'tis");
    });
});

describe('toReversed', () => {
    it('writes each word backwards with capitals kept in place', () => {
        assert.equal(rewriteWords('Hello world', toReversed), 'Olleh dlrow');
    });

    it('reverses by character, not code unit', () => {
        assert.equal(toReversed('café'), 'éfac');
    });
});

describe('toEmoji', () => {
    it('swaps known words, plurals included, and leaves unknown ones', () => {
        assert.equal(rewriteWords('Coffee before flights and books', toEmoji), '☕ before ✈️ and 📚');
    });
});

describe('toPigLatin', () => {
    it('moves the opening consonants to the end', () => {
        assert.equal(rewriteWords('Pig string queen square', toPigLatin), 'Igpay ingstray eenquay aresquay');
    });

    it('adds way to vowel words', () => {
        assert.equal(rewriteWords('apple I', toPigLatin), 'appleway Iway');
    });
});
