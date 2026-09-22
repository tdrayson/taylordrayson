import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { rewriteWords } from '../../resources/js/lib/textMode.js';
import { toEmoji } from '../../resources/js/lib/textModes/emoji.js';
import { toNumeronym } from '../../resources/js/lib/textModes/numeronym.js';
import { toPigLatin } from '../../resources/js/lib/textModes/pigLatin.js';
import { toPirate } from '../../resources/js/lib/textModes/pirate.js';
import { toReversed } from '../../resources/js/lib/textModes/reversed.js';

describe('rewriteWords', () => {
    it('transforms each word and keeps spacing, numbers and punctuation', () => {
        assert.equal(rewriteWords('Ran 5 miles, then coffee!', (word) => word.toUpperCase()), 'RAN 5 MILES, THEN COFFEE!');
    });

    it('leaves units and letters stuck to numbers alone', () => {
        assert.equal(rewriteWords('Ran 6.3 mi at 2:49pm, 25°C and 528 kcal', (word) => word.toUpperCase()), 'RAN 6.3 mi AT 2:49pm, 25°C AND 528 kcal');
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

    it('gives a TV episode two different emoji', () => {
        assert.notEqual(toEmoji('TV'), toEmoji('episode'));
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
