import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { clockParts, formatDate, formatTime, offsetMinutes } from '../../resources/js/lib/time.js';

// 24 August 2026, 14:05 UTC. London is on BST that day, so every zone below
// reads a different wall clock from the same instant.
const summer = new Date('2026-08-24T14:05:00Z');

// 24 January 2026, 14:05 UTC. London is back on GMT.
const winter = new Date('2026-01-24T14:05:00Z');

describe('clockParts', () => {
    it('reads the wall clock in the given zone, not the runner\'s', () => {
        assert.deepEqual(clockParts('Europe/London', summer), { hour: 15, minute: 5 });
        assert.deepEqual(clockParts('America/New_York', summer), { hour: 10, minute: 5 });
        assert.deepEqual(clockParts('Asia/Kolkata', summer), { hour: 19, minute: 35 });
    });

    it('follows the zone through a DST change', () => {
        assert.deepEqual(clockParts('Europe/London', winter), { hour: 14, minute: 5 });
    });

    it('gives midnight as hour zero', () => {
        assert.deepEqual(clockParts('Europe/London', new Date('2026-01-24T00:30:00Z')), { hour: 0, minute: 30 });
    });
});

describe('formatTime', () => {
    it('formats as the status bar writes it', () => {
        assert.equal(formatTime('Europe/London', summer), '3:05pm');
        assert.equal(formatTime('America/New_York', summer), '10:05am');
    });
});

describe('formatDate', () => {
    it('can land on a different day from the reader\'s', () => {
        // 00:30 in London on the 25th is still the 24th in New York.
        const crossover = new Date('2026-08-24T23:30:00Z');

        assert.match(formatDate('Europe/London', crossover), /25 August 2026$/);
        assert.match(formatDate('America/New_York', crossover), /24 August 2026$/);
    });
});

describe('offsetMinutes', () => {
    it('reports whole, half and negative offsets', () => {
        assert.equal(offsetMinutes('Europe/London', summer), 60);
        assert.equal(offsetMinutes('Europe/London', winter), 0);
        assert.equal(offsetMinutes('Asia/Kolkata', summer), 330);
        assert.equal(offsetMinutes('America/New_York', summer), -240);
    });

    it('reads UTC as zero, which formats without an offset to parse', () => {
        assert.equal(offsetMinutes('UTC', summer), 0);
    });
});
