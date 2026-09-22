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
        { size: 0.97, one: 'cricket bat', many: 'cricket bats' },
        { size: 3.05, one: 'Mini', many: 'Minis' },
        { size: 11.2, one: 'London bus', many: 'London buses' },
        { size: 20.1, one: 'cricket pitch', many: 'cricket pitches' },
        { size: 23.8, one: 'tennis court', many: 'tennis courts' },
        { size: 30, one: 'blue whale', many: 'blue whales' },
        { size: 50, one: 'Olympic pool', many: 'Olympic pools' },
        { size: 105, one: 'football pitch', many: 'football pitches' },
        { size: 244, one: 'Tower Bridge', many: 'Tower Bridges' },
        { size: 269, one: 'Titanic', many: 'Titanics' },
        { size: 315, one: 'Wembley arch', many: 'Wembley arches' },
        { size: 525, one: 'Brighton Pier', many: 'Brighton Piers' },
        { size: 1000, one: 'length of the Mall', many: 'lengths of the Mall' },
        { size: 1900, one: 'Oxford Street', many: 'Oxford Streets' },
        { size: 2737, one: 'Golden Gate Bridge', many: 'Golden Gate Bridges' },
        { size: 5000, one: 'parkrun', many: 'parkruns' },
        { size: 21097, one: 'half marathon', many: 'half marathons' },
        { size: 33300, one: 'Channel swim', many: 'Channel swims' },
        { size: 42195, one: 'marathon', many: 'marathons' },
        { size: 87000, one: 'London to Brighton', many: 'London to Brightons' },
        { size: 188000, one: 'lap of the M25', many: 'laps of the M25' },
        { size: 346000, one: 'length of the Thames', many: 'lengths of the Thames' },
        { size: 534000, one: 'London to Edinburgh', many: 'London to Edinburghs' },
        { size: 1407000, one: 'length of Britain', many: 'lengths of Britain' },
        { size: 5570000, one: 'hop to New York', many: 'hops to New York' },
        { size: 40075000, one: 'trip round the Earth', many: 'trips round the Earth' },
        { size: 384400000, one: 'trip to the Moon', many: 'trips to the Moon' },
    ],
    weight: [
        { size: 0.12, one: 'banana', many: 'bananas' },
        { size: 0.59, one: 'pint of milk', many: 'pints of milk' },
        { size: 1, one: 'bag of sugar', many: 'bags of sugar' },
        { size: 1.5, one: 'pineapple', many: 'pineapples' },
        { size: 4.5, one: 'house cat', many: 'house cats' },
        { size: 7, one: 'bowling ball', many: 'bowling balls' },
        { size: 12, one: 'corgi', many: 'corgis' },
        { size: 25, one: 'sack of potatoes', many: 'sacks of potatoes' },
        { size: 30, one: 'Labrador', many: 'Labradors' },
        { size: 70, one: 'washing machine', many: 'washing machines' },
        { size: 160, one: 'gorilla', many: 'gorillas' },
        { size: 480, one: 'grand piano', many: 'grand pianos' },
        { size: 650, one: 'Mini', many: 'Minis' },
        { size: 700, one: 'dairy cow', many: 'dairy cows' },
        { size: 1500, one: 'hippo', many: 'hippos' },
        { size: 2000, one: 'Transit van', many: 'Transit vans' },
        { size: 6000, one: 'African elephant', many: 'African elephants' },
        { size: 8000, one: 'T. rex', many: 'T. rexes' },
        { size: 12000, one: 'London bus', many: 'London buses' },
        { size: 150000, one: 'blue whale', many: 'blue whales' },
        { size: 400000, one: 'jumbo jet', many: 'jumbo jets' },
        { size: 10100000, one: 'Eiffel Tower', many: 'Eiffel Towers' },
        { size: 52310000, one: 'Titanic', many: 'Titanics' },
    ],
    height: [
        { size: 4.4, one: 'double-decker bus', many: 'double-decker buses' },
        { size: 5.5, one: 'giraffe', many: 'giraffes' },
        { size: 10, one: 'lamp post', many: 'lamp posts' },
        { size: 20, one: 'Angel of the North', many: 'Angels of the North' },
        { size: 52, one: "Nelson's Column", many: "Nelson's Columns" },
        { size: 93, one: 'Statue of Liberty', many: 'Statues of Liberty' },
        { size: 96, one: 'Big Ben', many: 'Big Bens' },
        { size: 135, one: 'London Eye', many: 'London Eyes' },
        { size: 158, one: 'Blackpool Tower', many: 'Blackpool Towers' },
        { size: 310, one: 'Shard', many: 'Shards' },
        { size: 330, one: 'Eiffel Tower', many: 'Eiffel Towers' },
        { size: 1085, one: 'Snowdon', many: 'Snowdons' },
        { size: 1345, one: 'Ben Nevis', many: 'Ben Nevises' },
        { size: 4808, one: 'Mont Blanc', many: 'Mont Blancs' },
        { size: 5895, one: 'Kilimanjaro', many: 'Kilimanjaros' },
        { size: 8849, one: 'Everest', many: 'Everests' },
    ],
    duration: [
        { size: 1, one: 'Mississippi', many: 'Mississippis' },
        { size: 180, one: 'kettle boil', many: 'kettle boils' },
        { size: 210, one: 'pop song', many: 'pop songs' },
        { size: 300, one: 'microwave dinner', many: 'microwave dinners' },
        { size: 360, one: 'boiled egg', many: 'boiled eggs' },
        { size: 1320, one: 'Friends episode', many: 'Friends episodes' },
        { size: 1800, one: 'EastEnders episode', many: 'EastEnders episodes' },
        { size: 5400, one: 'football match', many: 'football matches' },
        { size: 9000, one: 'Harry Potter film', many: 'Harry Potter films' },
        { size: 11640, one: 'Titanic', many: 'Titanics' },
        { size: 41160, one: 'Lord of the Rings binge', many: 'Lord of the Rings binges' },
        { size: 144000, one: 'working week', many: 'working weeks' },
    ],
    energy: [
        { size: 46, one: 'Jaffa Cake', many: 'Jaffa Cakes' },
        { size: 57, one: 'custard cream', many: 'custard creams' },
        { size: 84, one: 'chocolate digestive', many: 'chocolate digestives' },
        { size: 95, one: 'Freddo', many: 'Freddos' },
        { size: 105, one: 'banana', many: 'bananas' },
        { size: 140, one: 'packet of crisps', many: 'packets of crisps' },
        { size: 177, one: 'Creme Egg', many: 'Creme Eggs' },
        { size: 180, one: 'pint of lager', many: 'pints of lager' },
        { size: 228, one: 'Mars bar', many: 'Mars bars' },
        { size: 327, one: 'Greggs sausage roll', many: 'Greggs sausage rolls' },
        { size: 385, one: 'Pot Noodle', many: 'Pot Noodles' },
        { size: 493, one: 'Big Mac', many: 'Big Macs' },
        { size: 800, one: 'full English', many: 'full Englishes' },
        { size: 1000, one: 'Sunday roast', many: 'Sunday roasts' },
        { size: 1800, one: "Domino's pizza", many: "Domino's pizzas" },
        { size: 6000, one: 'Christmas Day', many: 'Christmas Days' },
    ],
    heartRate: [
        { size: 72, one: 'Bohemian Rhapsody', many: 'Bohemian Rhapsodies' },
        { size: 87, one: 'Wonderwall', many: 'Wonderwalls' },
        { size: 89, one: 'Mr Blue Sky', many: 'Mr Blue Skies' },
        { size: 103, one: "Stayin' Alive", many: "Stayin' Alives" },
        { size: 115, one: 'Uptown Funk', many: 'Uptown Funks' },
        { size: 117, one: 'Billie Jean', many: 'Billie Jeans' },
        { size: 128, one: 'Sweet Caroline', many: 'Sweet Carolines' },
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

