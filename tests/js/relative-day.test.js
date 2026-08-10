import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { relativeDay } from '../../resources/js/lib/format.js';

/**
 * A 'YYYY-MM-DD' string that many calendar days before today. Built by walking
 * the date rather than subtracting milliseconds, so the fixtures stay correct
 * across the 23 and 25 hour days either side of a DST change.
 */
function daysAgo(offset) {
    const date = new Date();
    date.setDate(date.getDate() - offset);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

describe('relativeDay', () => {
    it('names the recent days', () => {
        assert.equal(relativeDay(daysAgo(0)), 'Today');
        assert.equal(relativeDay(daysAgo(1)), 'Yesterday');
        assert.equal(relativeDay(daysAgo(2)), '2 days ago');
        assert.equal(relativeDay(daysAgo(7)), '7 days ago');
    });

    it('stays relative up to and including the cutoff, then gives up', () => {
        // Null is the signal to fall back to the absolute date, so the boundary
        // is the whole point of the function.
        assert.equal(relativeDay(daysAgo(13)), '13 days ago');
        assert.equal(relativeDay(daysAgo(14)), '14 days ago');
        assert.equal(relativeDay(daysAgo(15)), null);
        assert.equal(relativeDay(daysAgo(90)), null);
    });

    it('takes a different cutoff', () => {
        assert.equal(relativeDay(daysAgo(5), 7), '5 days ago');
        assert.equal(relativeDay(daysAgo(8), 7), null);
    });

    it('falls back rather than counting backwards into the future', () => {
        assert.equal(relativeDay(daysAgo(-1)), null);
    });

    it('reads a timestamp by the calendar day the reader is in', () => {
        // Podcasts, leaderboard scores and drafts carry a time, not a bare date.
        // Late last night is "Yesterday" this morning, not "Today", which is
        // what an elapsed-hours count would have said.
        const lateYesterday = new Date();
        lateYesterday.setDate(lateYesterday.getDate() - 1);
        lateYesterday.setHours(23, 45, 0, 0);

        assert.equal(relativeDay(lateYesterday.toISOString()), 'Yesterday');
        assert.equal(relativeDay(new Date().toISOString()), 'Today');
    });

    it('falls back on a missing or unparseable date', () => {
        assert.equal(relativeDay(null), null);
        assert.equal(relativeDay(''), null);
        assert.equal(relativeDay('not-a-date'), null);
    });
});
