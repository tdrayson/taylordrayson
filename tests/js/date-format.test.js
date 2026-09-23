import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { formatClock, formatDate, formatRange } from '../../resources/js/lib/dateFormat.js';

const fixtures = JSON.parse(readFileSync(new URL('../Fixtures/display-formats.json', import.meta.url)));
const dateFormats = ['short', 'long', 'dmy', 'mdy', 'iso'];

describe('dateFormat', () => {
    it('matches the PHP formatter for every time format', () => {
        for (const test of fixtures.time) {
            for (const format of ['12h', '24h']) {
                assert.equal(formatClock(test.at, format), test[format]);
            }
        }
    });

    it('matches the PHP formatter for every date format', () => {
        for (const test of fixtures.date) {
            for (const format of dateFormats) {
                assert.equal(formatDate(test.at, {}, format), test[format]);
            }
        }
    });

    it('matches the PHP formatter for every range', () => {
        for (const test of fixtures.range) {
            for (const format of dateFormats) {
                assert.equal(formatRange(test.start, test.end, format), test[format]);
            }
        }
    });

    it('reads a timestamp as written rather than in the browser zone', () => {
        assert.equal(formatClock('2026-09-22T23:30:00+09:00', '24h'), '23:30');
        assert.equal(formatClock('2026-09-22T23:30:00.000000Z', '12h'), '11:30pm');
    });
});
