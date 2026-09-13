/**
 * Masonry column presets keyed by the desktop (lg) column count. `counts` drives
 * the JS row-span maths; `cols` is the matching literal Tailwind class string
 * (Tailwind needs literal classes, so these are enumerated, not built). Smaller
 * breakpoints collapse automatically.
 *
 * Shared by PhotoGrid and its loading skeleton so a placeholder grid always has
 * the same column count as the tiles that replace it.
 */
export const PRESETS = {
    2: { counts: { base: 1, sm: 2, lg: 2 }, cols: 'grid-cols-1 sm:grid-cols-2' },
    3: { counts: { base: 2, sm: 2, lg: 3 }, cols: 'grid-cols-2 sm:grid-cols-2 lg:grid-cols-3' },
    4: { counts: { base: 2, sm: 3, lg: 4 }, cols: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4' },
    5: { counts: { base: 2, sm: 3, lg: 5 }, cols: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5' },
    6: { counts: { base: 3, sm: 4, lg: 6 }, cols: 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-6' },
};

export const DEFAULT_COLUMNS = 4;

/** The preset for a desktop column count, falling back to the default. */
export function preset(columns) {
    return PRESETS[columns] ?? PRESETS[DEFAULT_COLUMNS];
}

/** Grid geometry, shared so a skeleton tile lines up with a real one. */
export const GAP = 12; // matches gap-3
export const ROW = 8; // grid-auto-rows base unit

/**
 * Rows a tile spans for a given aspect ratio, matching the CSS grid geometry
 * above. A span of n renders `n * (ROW + GAP) - GAP` pixels tall.
 *
 * @param {number} columnWidth - measured column width in px
 * @param {number} ratio - height / width of the image
 * @returns {number}
 */
export function spanForRatio(columnWidth, ratio) {
    return Math.max(1, Math.round((columnWidth * ratio + GAP) / (ROW + GAP)));
}

/** Span of a square tile at a typical column width, the fallback shape. */
export const SQUARE_SPAN = 10;

/**
 * Aspect ratios a placeholder grid cycles through: portrait, landscape and
 * square in the rough proportion a phone camera roll produces, so the skeleton
 * staggers like real masonry instead of reading as a uniform table.
 */
export const PLACEHOLDER_RATIOS = [4 / 3, 3 / 4, 1, 4 / 3, 3 / 4, 16 / 9, 1, 4 / 3];
