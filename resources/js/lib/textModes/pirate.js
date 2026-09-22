import { keyOf, matchCase } from './words.js';

const PIRATE = {
    hello: 'ahoy',
    hi: 'ahoy',
    hey: 'ahoy',
    yes: 'aye',
    yeah: 'aye',
    no: 'nay',
    my: 'me',
    you: 'ye',
    your: 'yer',
    "you're": 'ye be',
    "it's": "'tis",
    is: 'be',
    are: 'be',
    the: "th'",
    of: "o'",
    and: "an'",
    for: 'fer',
    with: "wi'",
    over: "o'er",
    never: "ne'er",
    ever: "e'er",
    before: 'afore',
    them: "'em",
    friend: 'matey',
    friends: 'hearties',
    mate: 'matey',
    people: 'landlubbers',
    everyone: 'all hands',
    boy: 'lad',
    girl: 'lass',
    money: 'doubloons',
    cash: 'doubloons',
    food: 'grub',
    drink: 'swig',
    drinks: 'swigs',
    coffee: 'grog',
    beer: 'grog',
    stop: 'avast',
    wow: 'blimey',
    look: 'behold',
    think: 'reckon',
    small: 'wee',
    great: 'mighty',
    very: 'mighty',
    old: 'barnacled',
    home: 'port',
    car: 'ship',
    trip: 'voyage',
    trips: 'voyages',
    flight: 'voyage',
    flights: 'voyages',
    travel: 'sail',
    map: 'treasure map',
    maps: 'treasure maps',
    stats: 'plunder',
    story: 'yarn',
    stories: 'yarns',
    articles: 'tales',
    notes: 'scribblins',
    timeline: "captain's log",
    search: 'seek',
    photos: 'portraits',
    settings: "riggin'",
};

/**
 * Say a word like a pirate: known words are swapped, and running becomes runnin'.
 * @param {string} word
 * @returns {string}
 */
export function toPirate(word) {
    const key = keyOf(word);

    if (PIRATE[key]) {
        return matchCase(word, PIRATE[key]);
    }

    return key.length > 4 && key.endsWith('ing') ? `${word.slice(0, -1)}'` : word;
}
