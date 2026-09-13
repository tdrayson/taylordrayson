import { authoringTypes, linkTypes, timelineTypes } from './types.generated.js';

/**
 * Visual metadata for every data type, keyed by the card's `type` string.
 *
 * The table itself lives in PHP (App\Support\TypeCatalogue) and reaches here as
 * types.generated.js, written by `php artisan types:sync`. Edit the catalogue,
 * not the generated file: a Pest test fails when the two drift apart.
 *
 * `icon` is an icon-registry name, `href` is the type's archive, and `accent` is
 * the --color-* token key, which matches the type key except where it diverges
 * server-side (calorie -> food).
 */

/**
 * The timeline data types, which are also the list of type archives: anything
 * here becomes a destination in the command palette.
 */
export { timelineTypes };

/** Every type a card, link preview or icon lookup can be handed. */
export const entryTypes = { ...timelineTypes, ...linkTypes, ...authoringTypes };

/**
 * Metadata for a type key, falling back to a note's glyph for anything unknown
 * so a new server-side type renders as a plain entry rather than breaking.
 *
 * @param {string} type a type key, e.g. 'activity' or 'tag'
 * @returns {{icon: string, label: string, accent: string, plural?: string, href?: string, keywords?: string}}
 */
export function entryType(type) {
    return entryTypes[type] ?? { icon: 'StickyNote02Icon', label: type, accent: 'note' };
}
