// Full forms for abbreviated stat units, so a unit suffix can render inside an
// <abbr> that carries its expansion for screen readers and hover. Shared by the
// StatGrid and StatCards components. Unknown units get no title.
export const UNIT_TITLES = {
    mi: 'miles',
    km: 'kilometres',
    kcal: 'kilocalories',
    'kcal/day': 'kilocalories per day',
    bpm: 'beats per minute',
    m: 'metres',
    L: 'litres',
};

/**
 * The full form for an abbreviated unit, or null when there's no expansion.
 * @param {string} unit
 * @returns {string|null}
 */
export function unitTitle(unit) {
    return UNIT_TITLES[unit] ?? null;
}
