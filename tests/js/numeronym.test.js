import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { numeronymise } from '../../resources/js/lib/numeronym.js';

describe('numeronymise', () => {
    it('shortens words of four letters or more and keeps the rest of the text', () => {
        assert.equal(numeronymise('Accessibility, internationalisation & the web!'), 'A11y, i18n & the web!');
    });

    it('counts letters rather than code units and ignores apostrophes', () => {
        assert.equal(numeronymise("don't café"), "d2t c2é");
    });
});
