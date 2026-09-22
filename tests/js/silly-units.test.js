import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { sillyDistance, sillyWeight } from '../../resources/js/lib/sillyUnits.js';

describe('sillyDistance', () => {
    it('counts in the largest yardstick the distance fills', () => {
        assert.deepEqual(sillyDistance(5000), { value: '1', unit: 'parkrun' });
        assert.deepEqual(sillyDistance(21097), { value: '4.2', unit: 'parkruns' });
        assert.deepEqual(sillyDistance(135000), { value: '3.2', unit: 'marathons' });
        assert.deepEqual(sillyDistance(1000), { value: '9.5', unit: 'football pitches' });
        assert.deepEqual(sillyDistance(50), { value: '4.5', unit: 'London buses' });
    });

    it('reaches the Moon for lifetime flight totals', () => {
        assert.deepEqual(sillyDistance(800000000), { value: '2.1', unit: 'trips to the Moon' });
        assert.deepEqual(sillyDistance(100000000), { value: '2.5', unit: 'trips round the Earth' });
    });

    it('drops the decimal from whole counts and from counts of ten or more', () => {
        assert.deepEqual(sillyDistance(84390), { value: '2', unit: 'marathons' });
        assert.deepEqual(sillyDistance(30000000), { value: '21', unit: 'lengths of Britain' });
    });

    it('falls back to bananas below the smallest yardstick', () => {
        assert.deepEqual(sillyDistance(0.09), { value: '0.5', unit: 'bananas' });
    });
});

describe('sillyWeight', () => {
    it('weighs a set in corgis and a session in buses', () => {
        assert.deepEqual(sillyWeight(60), { value: '5', unit: 'corgis' });
        assert.deepEqual(sillyWeight(12000), { value: '1', unit: 'London bus' });
        assert.deepEqual(sillyWeight(2400000), { value: '16', unit: 'blue whales' });
    });

    it('keeps light things in bananas', () => {
        assert.deepEqual(sillyWeight(2.5), { value: '21', unit: 'bananas' });
    });
});
