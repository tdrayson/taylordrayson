import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { optionsEqual } from '../../resources/js/lib/editor/optionsEqual.js';
import { isFourDigitYear } from '../../resources/js/lib/editor/period.js';

describe('optionsEqual', () => {
    it('matches two option sets built in a different key order', () => {
        assert.equal(
            optionsEqual({ type: 'calorie', period: 'this-year' }, { period: 'this-year', type: 'calorie' }),
            true,
        );
    });

    it('rejects a different value under the same key', () => {
        assert.equal(optionsEqual({ type: 'calorie' }, { type: 'sleep' }), false);
    });

    it('rejects a different number of keys', () => {
        assert.equal(optionsEqual({ type: 'calorie' }, { type: 'calorie', period: 'this-year' }), false);
    });

    it('treats missing objects as empty', () => {
        assert.equal(optionsEqual(undefined, {}), true);
        assert.equal(optionsEqual({}, undefined), true);
    });

    it('treats an explicit undefined value as a distinct key from one missing entirely', () => {
        // `resolvedOptions` never builds one of these itself; pins the
        // contract so a future caller cannot rely on the two being confused.
        assert.equal(optionsEqual({ type: undefined }, {}), false);
    });
});

describe('isFourDigitYear', () => {
    it('accepts a bare four-digit year', () => {
        assert.equal(isFourDigitYear('2023'), true);
    });

    it('rejects a named preset', () => {
        assert.equal(isFourDigitYear('this-year'), false);
    });

    it('rejects a year with the wrong digit count', () => {
        assert.equal(isFourDigitYear('23'), false);
        assert.equal(isFourDigitYear('20233'), false);
    });

    it('rejects an empty or missing value', () => {
        assert.equal(isFourDigitYear(''), false);
        assert.equal(isFourDigitYear(undefined), false);
    });
});
