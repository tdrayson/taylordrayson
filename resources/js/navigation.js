import { timelineTypes } from './entryTypes.js';

// Icons are icon-registry name strings (resolved by Icon.vue), so this module
// carries no direct hugeicons imports.

/**
 * Primary page destinations for the command palette and navigation.
 */
export const pageCommands = [
    { label: 'Timeline', href: '/', icon: 'Home03Icon', keywords: 'home feed entries' },
    { label: 'Now', href: '/now', icon: 'Clock01Icon', keywords: 'current status live' },
    { label: 'Photos', href: '/photos', icon: 'Image01Icon', keywords: 'gallery pictures' },
    { label: 'Data stories', href: '/stories', icon: 'BookOpen01Icon', keywords: 'long reads writing analysis' },
    // The flight globe, which is the only map page that exists; the keywords
    // carry the searches that used to land on a bare /map.
    { label: 'Flight map', href: '/flights/map', icon: 'MapsLocation01Icon', keywords: 'map places location flights globe' },
    { label: 'Tags', href: '/tags', icon: 'Tag01Icon', keywords: 'topics labels taxonomy' },
    { label: 'More', href: '/more', icon: 'Menu01Icon', keywords: 'directory everything types index' },
    { label: 'About', href: '/about', icon: 'UserIcon', keywords: 'bio profile me' },
    { label: 'Advanced search', href: '/search', icon: 'Search01Icon', keywords: 'query builder filter advanced' },
];

/**
 * One destination per timeline data type (activities, sleep, food, flights, …).
 *
 * `keywords` are editorial synonyms carried on the type itself, so a query lands
 * on the right section when the word never appears as a taxonomy value (those
 * specific values, e.g. a "Run" activity type, are surfaced live by the server).
 */
export const archiveCommands = Object.values(timelineTypes)
    .filter((type) => type.href)
    .map((type) => ({ label: type.label, href: type.href, icon: type.icon, keywords: type.keywords }));

/**
 * The types offered before anything is typed. The rest of the authorable types
 * are still reachable, but only once the query names them.
 */
const QUICK_CREATE_TYPES = ['note', 'article', 'fuel', 'event'];

/**
 * Authoring destinations for the command palette, built from the types the
 * server says can be written by hand.
 *
 * Signed-in only: every route here sits behind the auth middleware, so a public
 * palette is handed an empty list and shows no Create section at all.
 *
 * @param {Array<{type: string, label: string, icon: string}>} types authorable types
 * @returns {Array<object>} palette items, `quick` marking the ones worth showing unprompted
 */
export function createCommands(types) {
    return [
        ...types.map((type) => ({
            label: `New ${type.label.toLowerCase()}`,
            href: `/new/${type.type}`,
            icon: type.icon,
            keywords: `write add create compose ${type.label}`,
            quick: QUICK_CREATE_TYPES.includes(type.type),
        })),
        { label: 'New entry', href: '/new', icon: 'PlusSignIcon', keywords: 'write add create compose quick capture anything', quick: true },
        { label: 'Drafts', href: '/drafts', icon: 'File02Icon', keywords: 'unpublished unfinished work in progress', quick: true },
    ];
}
