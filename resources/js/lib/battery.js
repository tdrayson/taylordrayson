/**
 * The battery level at or below which iOS treats the battery as low.
 */
export const LOW_BATTERY_THRESHOLD = 0.2;

/**
 * Reduce a battery reading to one state token, following iOS's precedence:
 * Low Power Mode beats low, which beats charging, which beats idle.
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
 * cannot drift apart. `idle` is deliberately absent: the glyph inherits the
 * surrounding text colour while the tile uses its own foreground.
 */
export const BATTERY_COLOURS = {
    'low-power': 'var(--color-battery-power)',
    low: 'var(--color-battery-low)',
    charging: 'var(--color-battery-charging)',
};
