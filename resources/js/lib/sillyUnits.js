import { number } from './format.js';

/** A yardstick reads well from one of it up to just under this many. */
const MAX_COUNT = 40;

/**
 * Daft yardsticks per kind of quantity, smallest first, sized in the kind's base
 * unit: metres, kilograms, seconds, kilocalories or beats per minute.
 */
const YARDSTICKS = {
    distance: [
        { size: 0.18, one: 'banana', many: 'bananas' },
        { size: 0.6, one: 'sausage dog', many: 'sausage dogs' },
        { size: 11.2, one: 'London bus', many: 'London buses' },
        { size: 30, one: 'blue whale', many: 'blue whales' },
        { size: 50, one: 'Olympic pool', many: 'Olympic pools' },
        { size: 105, one: 'football pitch', many: 'football pitches' },
        { size: 244, one: 'Tower Bridge', many: 'Tower Bridges' },
        { size: 525, one: 'Brighton Pier', many: 'Brighton Piers' },
        { size: 5000, one: 'parkrun', many: 'parkruns' },
        { size: 33300, one: 'Channel swim', many: 'Channel swims' },
        { size: 42195, one: 'marathon', many: 'marathons' },
        { size: 188000, one: 'lap of the M25', many: 'laps of the M25' },
        { size: 346000, one: 'length of the Thames', many: 'lengths of the Thames' },
        { size: 1407000, one: 'length of Britain', many: 'lengths of Britain' },
        { size: 40075000, one: 'trip round the Earth', many: 'trips round the Earth' },
        { size: 384400000, one: 'trip to the Moon', many: 'trips to the Moon' },
    ],
    weight: [
        { size: 0.12, one: 'banana', many: 'bananas' },
        { size: 1.5, one: 'pineapple', many: 'pineapples' },
        { size: 7, one: 'bowling ball', many: 'bowling balls' },
        { size: 12, one: 'corgi', many: 'corgis' },
        { size: 25, one: 'sack of potatoes', many: 'sacks of potatoes' },
        { size: 480, one: 'grand piano', many: 'grand pianos' },
        { size: 650, one: 'Mini', many: 'Minis' },
        { size: 1500, one: 'hippo', many: 'hippos' },
        { size: 8000, one: 'T. rex', many: 'T. rexes' },
        { size: 12000, one: 'London bus', many: 'London buses' },
        { size: 150000, one: 'blue whale', many: 'blue whales' },
        { size: 10100000, one: 'Eiffel Tower', many: 'Eiffel Towers' },
        { size: 52310000, one: 'Titanic', many: 'Titanics' },
    ],
    height: [
        { size: 4.4, one: 'double-decker bus', many: 'double-decker buses' },
        { size: 5.5, one: 'giraffe', many: 'giraffes' },
        { size: 52, one: "Nelson's Column", many: "Nelson's Columns" },
        { size: 96, one: 'Big Ben', many: 'Big Bens' },
        { size: 158, one: 'Blackpool Tower', many: 'Blackpool Towers' },
        { size: 310, one: 'Shard', many: 'Shards' },
        { size: 1085, one: 'Snowdon', many: 'Snowdons' },
        { size: 1345, one: 'Ben Nevis', many: 'Ben Nevises' },
        { size: 8849, one: 'Everest', many: 'Everests' },
    ],
    duration: [
        { size: 1, one: 'Mississippi', many: 'Mississippis' },
        { size: 180, one: 'kettle boil', many: 'kettle boils' },
        { size: 210, one: 'pop song', many: 'pop songs' },
        { size: 1320, one: 'Friends episode', many: 'Friends episodes' },
        { size: 5400, one: 'football match', many: 'football matches' },
        { size: 11640, one: 'Titanic', many: 'Titanics' },
        { size: 41160, one: 'Lord of the Rings binge', many: 'Lord of the Rings binges' },
    ],
    energy: [
        { size: 46, one: 'Jaffa Cake', many: 'Jaffa Cakes' },
        { size: 84, one: 'chocolate digestive', many: 'chocolate digestives' },
        { size: 105, one: 'banana', many: 'bananas' },
        { size: 228, one: 'Mars bar', many: 'Mars bars' },
        { size: 327, one: 'Greggs sausage roll', many: 'Greggs sausage rolls' },
        { size: 493, one: 'Big Mac', many: 'Big Macs' },
        { size: 1000, one: 'Sunday roast', many: 'Sunday roasts' },
    ],
    heartRate: [
        { size: 72, one: 'Bohemian Rhapsody', many: 'Bohemian Rhapsodies' },
        { size: 103, one: "Stayin' Alive", many: "Stayin' Alives" },
        { size: 148, one: 'Mr Brightside', many: 'Mr Brightsides' },
    ],
};

/**
 * A stable pick from 0 to length - 1 for an amount, so the same value always
 * reads the same way on the server and in the browser.
 * @param {number} amount
 * @param {number} length
 * @returns {number}
 */
function pick(amount, length) {
    let hash = Math.round(amount * 10);
    hash = Math.imul(hash ^ (hash >>> 16), 0x45d9f3b);
    hash = Math.imul(hash ^ (hash >>> 16), 0x45d9f3b);

    return ((hash ^ (hash >>> 16)) >>> 0) % length;
}

/**
 * The yardsticks an amount fills between once and MAX_COUNT times, or the
 * nearest end of the ladder when it is off either end.
 * @param {number} amount
 * @param {Array<{size: number, one: string, many: string}>} ladder
 * @returns {Array<{size: number, one: string, many: string}>}
 */
function fitting(amount, ladder) {
    const fits = ladder.filter((step) => amount >= step.size && amount / step.size < MAX_COUNT);

    if (fits.length) {
        return fits;
    }

    return [ladder.findLast((step) => amount >= step.size) ?? ladder[0]];
}

/**
 * A quantity in daft units, e.g. 42195 metres could be 1 marathon or 8.4 parkruns.
 * Counts under ten keep one decimal place.
 * @param {'distance'|'weight'|'height'|'duration'|'energy'|'heartRate'} kind
 * @param {number} amount In the kind's base unit.
 * @returns {{value: string, unit: string}}
 */
export function sillyMeasure(kind, amount) {
    const absolute = Math.abs(Number(amount));
    const options = fitting(absolute, YARDSTICKS[kind]);
    const yardstick = options[pick(absolute, options.length)];
    const count = absolute / yardstick.size;
    const value = number(count, count < 10 ? 1 : 0).replace(/\.0$/, '');

    return { value, unit: value === '1' ? yardstick.one : yardstick.many };
}

