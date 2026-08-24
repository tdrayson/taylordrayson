import { entryTypes } from './entryTypes.js';

// Icons are icon-registry name strings (resolved by Icon.vue), so this module
// carries no direct hugeicons imports.

/**
 * Primary page destinations for the command palette and navigation.
 */
export const pageCommands = [
    { label: 'Timeline', href: '/', icon: 'Home01Icon', keywords: 'home feed entries' },
    { label: 'Now', href: '/now', icon: 'Clock01Icon', keywords: 'current status live' },
    { label: 'Photos', href: '/photos', icon: 'Image01Icon', keywords: 'gallery pictures' },
    { label: 'Data stories', href: '/stories', icon: 'BookOpen01Icon', keywords: 'long reads writing analysis' },
    // The flight globe, which is the only map page that exists; the keywords
    // carry the searches that used to land on a bare /map.
    { label: 'Flight map', href: '/flights/map', icon: 'MapsLocation01Icon', keywords: 'map places location flights globe' },
    { label: 'More', href: '/more', icon: 'Menu01Icon', keywords: 'directory everything types index' },
    { label: 'About', href: '/about', icon: 'UserIcon', keywords: 'bio profile me' },
    { label: 'Advanced search', href: '/search', icon: 'Search01Icon', keywords: 'query builder filter advanced' },
];

/**
 * Editorial synonyms for the archive *index* pages, so a query lands on the
 * right section when the word never appears as a taxonomy value (those specific
 * values, e.g. a "Run" activity type, are surfaced live by the server instead).
 */
const archiveKeywords = {
    activity: 'workout exercise sport',
    sleep: 'rest bed nap',
    calorie: 'food eat meal nutrition',
    media: 'watch movie film tv show book reading',
    event: 'ticket gig concert',
    appearance: 'talk speaking interview',
    podcast: 'podcast tww episode',
    flight: 'fly travel trip airport',
    checkin: 'place location visited',
    fuel: 'petrol gas diesel',
    project: 'build side product',
    article: 'blog post writing read',
    note: 'memo journal thought',
};

/**
 * One destination per timeline data type (activities, sleep, food, flights, …).
 */
export const archiveCommands = Object.entries(entryTypes)
    .filter(([, type]) => type.href)
    .map(([key, type]) => ({ label: type.label, href: type.href, icon: type.icon, keywords: archiveKeywords[key] }));
