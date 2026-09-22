import { number } from './format.js';

/**
 * Daft yardsticks, smallest first, each sized in metres or kilograms. A value is
 * counted in the largest one it fills at least once, so the count stays small.
 */
const DISTANCES = [
    { size: 0.18, one: 'banana', many: 'bananas' },
    { size: 11.2, one: 'London bus', many: 'London buses' },
    { size: 105, one: 'football pitch', many: 'football pitches' },
    { size: 5000, one: 'parkrun', many: 'parkruns' },
    { size: 42195, one: 'marathon', many: 'marathons' },
    { size: 188000, one: 'lap of the M25', many: 'laps of the M25' },
    { size: 1407000, one: 'length of Britain', many: 'lengths of Britain' },
    { size: 40075000, one: 'trip round the Earth', many: 'trips round the Earth' },
    { size: 384400000, one: 'trip to the Moon', many: 'trips to the Moon' },
];

const WEIGHTS = [
    { size: 0.12, one: 'banana', many: 'bananas' },
    { size: 12, one: 'corgi', many: 'corgis' },
    { size: 650, one: 'Mini', many: 'Minis' },
    { size: 12000, one: 'London bus', many: 'London buses' },
    { size: 150000, one: 'blue whale', many: 'blue whales' },
    { size: 10100000, one: 'Eiffel Tower', many: 'Eiffel Towers' },
];

/**
 * Count an amount in the largest yardstick it fills, to 1dp under ten.
 * @param {number} amount
 * @param {Array<{size: number, one: string, many: string}>} ladder
 * @returns {{value: string, unit: string}}
 */
function measure(amount, ladder) {
    const yardstick = ladder.findLast((step) => amount >= step.size) ?? ladder[0];
    const count = amount / yardstick.size;
    const value = number(count, count < 10 ? 1 : 0).replace(/\.0$/, '');

    return { value, unit: value === '1' ? yardstick.one : yardstick.many };
}

/**
 * A distance in daft units, e.g. 42195 metres is 1 marathon.
 * @param {number} metres
 * @returns {{value: string, unit: string}}
 */
export function sillyDistance(metres) {
    return measure(Math.abs(Number(metres)), DISTANCES);
}

/**
 * A weight in daft units, e.g. 24 kilograms is 2 corgis.
 * @param {number} kg
 * @returns {{value: string, unit: string}}
 */
export function sillyWeight(kg) {
    return measure(Math.abs(Number(kg)), WEIGHTS);
}
