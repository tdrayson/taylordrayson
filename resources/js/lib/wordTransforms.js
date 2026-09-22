/** Words shorter than this gain nothing: four letters is the first that shrinks (X2Y). */
const MIN_NUMERONYM_LENGTH = 4;

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

const EMOJI = {
    run: '🏃',
    ran: '🏃',
    running: '🏃',
    walk: '🚶',
    walked: '🚶',
    walking: '🚶',
    ride: '🚴',
    cycled: '🚴',
    cycling: '🚴',
    bike: '🚲',
    swim: '🏊',
    swimming: '🏊',
    gym: '🏋️',
    workout: '💪',
    flight: '✈️',
    flew: '✈️',
    plane: '✈️',
    airport: '🛫',
    car: '🚗',
    fuel: '⛽',
    petrol: '⛽',
    train: '🚆',
    bus: '🚌',
    coffee: '☕',
    tea: '🍵',
    beer: '🍺',
    wine: '🍷',
    food: '🍔',
    pizza: '🍕',
    breakfast: '🥞',
    lunch: '🥪',
    dinner: '🍽️',
    cake: '🍰',
    sleep: '😴',
    slept: '😴',
    book: '📚',
    read: '📖',
    film: '🎬',
    movie: '🎬',
    episode: '📺',
    tv: '📺',
    music: '🎵',
    podcast: '🎧',
    game: '🎮',
    photo: '📷',
    note: '📝',
    article: '📰',
    story: '📖',
    map: '🗺️',
    place: '📍',
    event: '🎟️',
    home: '🏠',
    work: '💼',
    time: '⏰',
    today: '📅',
    week: '🗓️',
    year: '📆',
    stats: '📊',
    search: '🔍',
    settings: '⚙️',
    more: '➕',
    now: '👉',
    love: '❤️',
    heart: '❤️',
    happy: '😊',
    sad: '😢',
    fun: '🎉',
    party: '🎉',
    sun: '☀️',
    rain: '🌧️',
    snow: '❄️',
    weather: '🌦️',
    hot: '🔥',
    cold: '🥶',
    dog: '🐶',
    cat: '🐱',
    world: '🌍',
    star: '⭐',
    money: '💷',
    phone: '📱',
    computer: '💻',
    code: '💻',
    idea: '💡',
    birthday: '🎂',
    christmas: '🎄',
    yes: '👍',
    no: '👎',
    ok: '👌',
};

const VOWELS = /^[aeiou]/i;

/**
 * Carry the capitals of a source word onto its replacement: HELLO gives AHOY, Hello gives Ahoy.
 * @param {string} source
 * @param {string} replacement
 * @returns {string}
 */
function matchCase(source, replacement) {
    if (source.length > 1 && source === source.toUpperCase()) {
        return replacement.toUpperCase();
    }

    const first = source[0];

    return first !== first.toLowerCase() ? replacement[0].toUpperCase() + replacement.slice(1) : replacement;
}

/**
 * A word's dictionary key: lower case, curly apostrophes straightened.
 * @param {string} word
 * @returns {string}
 */
function keyOf(word) {
    return word.toLowerCase().replaceAll('’', "'");
}

/**
 * Shorten one word to its numeronym: accessibility becomes a11y.
 * @param {string} word
 * @returns {string}
 */
export function toNumeronym(word) {
    const letters = [...word.replace(/[^\p{L}]/gu, '')];

    if (letters.length < MIN_NUMERONYM_LENGTH) {
        return word;
    }

    return `${letters[0]}${letters.length - 2}${letters.at(-1)}`;
}

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

/**
 * Write a word backwards, keeping its capitals where they were: Hello becomes Olleh.
 * @param {string} word
 * @returns {string}
 */
export function toReversed(word) {
    const source = [...word];

    return source
        .toReversed()
        .map((char, index) => (source[index] !== source[index].toLowerCase() ? char.toUpperCase() : char.toLowerCase()))
        .join('');
}

/**
 * Swap a word for its emoji when we know one, trying the singular too.
 * @param {string} word
 * @returns {string}
 */
export function toEmoji(word) {
    const key = keyOf(word);

    return EMOJI[key] ?? (key.endsWith('s') ? EMOJI[key.slice(0, -1)] : null) ?? word;
}

/**
 * Pig Latin: the opening consonants move to the end and take "ay", so pig becomes igpay.
 * @param {string} word
 * @returns {string}
 */
export function toPigLatin(word) {
    const lower = word.toLowerCase();

    if (VOWELS.test(lower)) {
        return `${word}way`;
    }

    const onset = lower.match(/^(?:[^aeiouy]*qu|[^aeiou][^aeiouy]*)/)?.[0] ?? '';

    return matchCase(word, `${lower.slice(onset.length)}${onset}ay`);
}

/** Each text mode's word transform, keyed by the setting's value. */
export const WORD_TRANSFORMS = {
    numeronym: toNumeronym,
    pirate: toPirate,
    reversed: toReversed,
    emoji: toEmoji,
    'pig-latin': toPigLatin,
};
