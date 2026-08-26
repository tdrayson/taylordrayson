/**
 * Wall-clock arithmetic for the editor's date fields.
 *
 * A stored timestamp is a local reading, never an instant: parsing one through
 * `new Date(string)` reads it as UTC and walks it by the browser's offset every
 * time it is written back. Everything here works on the digits instead.
 */

const pad = (n) => String(n).padStart(2, '0');

const PATTERN = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/;

/**
 * Split a stored value into its date and time halves, both empty when it is
 * unset or malformed.
 *
 * @param {string|null} value 'YYYY-MM-DD HH:mm:ss' or the T-separated form.
 * @returns {{date: string, time: string}}
 */
export function wallClockParts(value) {
    const match = String(value ?? '').match(PATTERN);

    return match
        ? { date: `${match[1]}-${match[2]}-${match[3]}`, time: `${match[4]}:${match[5]}` }
        : { date: '', time: '' };
}

/**
 * A stored value as a Date carrying those same digits in the browser's zone,
 * so it can be shifted and read back without changing what it says.
 *
 * @param {string|null} value
 * @returns {Date|null}
 */
export function toWallClockDate(value) {
    const match = String(value ?? '').match(PATTERN);

    if (! match) {
        return null;
    }

    const [, year, month, day, hour, minute] = match.map(Number);

    return new Date(year, month - 1, day, hour, minute);
}

/**
 * A Date written back in the shape the field stores.
 *
 * @param {Date} date
 * @returns {string}
 */
export function stampWallClock(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:00`;
}

/**
 * A stored value moved by some minutes, or null when there is nothing to move.
 *
 * @param {string|null} value
 * @param {number} minutes
 * @returns {string|null}
 */
export function shiftWallClock(value, minutes) {
    const from = toWallClockDate(value);

    return from === null ? null : stampWallClock(new Date(from.getTime() + minutes * 60000));
}
