import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { YARDSTICKS, sillyMeasure } from '../../resources/js/lib/sillyUnits.js';

const count = ({ value }) => Number(value.replace(/,/g, ''));

describe('sillyMeasure', () => {
    it('keeps every count between one and forty', () => {
        for (const [kind, amount] of [['distance', 7300], ['weight', 60], ['height', 420], ['duration', 3000], ['energy', 2145], ['heartRate', 142]]) {
            const measure = sillyMeasure(kind, amount);

            assert.ok(count(measure) >= 1 && count(measure) < 40, `${kind}: ${measure.value} ${measure.unit}`);
        }
    });

    it('always reads the same value the same way', () => {
        assert.deepEqual(sillyMeasure('distance', 10100), sillyMeasure('distance', 10100));
    });

    it('varies the yardstick between values', () => {
        const units = new Set(Array.from({ length: 30 }, (_, i) => sillyMeasure('distance', 5000 + i * 173).unit));

        assert.ok(units.size >= 3, [...units].join(', '));
    });

    it('uses the singular for exactly one', () => {
        assert.deepEqual(sillyMeasure('weight', 0.12), { value: '1', unit: 'banana' });
    });

    it('falls back to the ends of the ladder', () => {
        assert.deepEqual(sillyMeasure('weight', 0.06), { value: '0.5', unit: 'bananas' });
        assert.equal(sillyMeasure('distance', 1e12).unit, 'trips to the Moon');
    });

    it('drops the decimal from counts of ten or more', () => {
        assert.match(sillyMeasure('weight', 2400).value, /^\d+$|^\d\.\d$/);
    });

    it('lists every ladder smallest first', () => {
        for (const [kind, ladder] of Object.entries(YARDSTICKS)) {
            const sizes = ladder.map((step) => step.size);

            assert.deepEqual(sizes, sizes.toSorted((a, b) => a - b), kind);
        }
    });

    it('never uses the same thing for two kinds of quantity', () => {
        const seen = new Map();

        for (const [kind, ladder] of Object.entries(YARDSTICKS)) {
            for (const { one } of ladder) {
                const subject = one.toLowerCase().split(' ').at(-1);

                assert.ok(!seen.has(subject) || seen.get(subject) === kind, `${one} is a ${kind} and a ${seen.get(subject)}`);
                seen.set(subject, kind);
            }
        }
    });
});
