/**
 * The battery level at or below which iOS treats the battery as low.
 */
export const LOW_BATTERY_THRESHOLD = 0.2;

/**
 * Reduce a battery reading to one state token, following iOS's precedence:
 * Low Power Mode beats low, which beats charging, which beats idle.
 *
 * Shared because the same battery is drawn in two very different shapes, the
 * status bar's 14px SVG and the /now tile, and each had its own copy of this
 * rule. The copies had already drifted on which colour Low Power Mode uses,
 * which is exactly the kind of thing a duplicated rule does over time.
 *
 * Only the rule is shared. Each component still picks its own colours, since
 * a tile and a 14px glyph do not want the same palette.
 *
 * @param {{ level: number, charging?: boolean, lowPower?: boolean }} reading
 *   `level` is a 0-1 fraction; callers holding a percentage divide by 100.
 * @return {'low-power'|'low'|'charging'|'idle'}
 */
export function batteryState({ level, charging = false, lowPower = false }) {
    if (lowPower) {
        return 'low-power';
    }

    if (level <= LOW_BATTERY_THRESHOLD) {
        return 'low';
    }

    return charging ? 'charging' : 'idle';
}

/**
 * The colour for each state, shared so the status bar glyph and the /now tile
 * cannot drift apart again. They previously disagreed on two of the three.
 *
 * `idle` is deliberately absent: the glyph inherits the surrounding text
 * colour while the tile uses its own foreground, and that difference is real
 * rather than an oversight.
 */
export const BATTERY_COLOURS = {
    'low-power': '#FF9500',
    low: '#FA3532',
    charging: '#1BC95A',
};
