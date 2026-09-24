/**
 * Visitor-facing times and dates in the formats chosen in settings. Mirrors
 * app/Support/DisplayFormat.php; tests/Fixtures/display-formats.json holds both
 * to the same output.
 *
 * Works from wall-clock parts and never converts zones: a string is read as
 * written, since stored timestamps already hold the entry's local clock.
 */

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// Plain getters rather than the settings store, so this file stays importable
// under node --test. useDateFormat.js swaps in the reactive ones.
let formats = { time: () => '12h', date: () => 'short' };

/**
 * Point the formatters at the visitor's settings.
 * @param {{time: () => string, date: () => string}} getters Read on every call, so a reactive source re-renders its callers.
 * @returns {void}
 */
export function useDisplayFormats(getters) {
    formats = getters;
}

const pad = (value) => String(value).padStart(2, '0');

/**
 * The wall-clock parts of a value, or null if it cannot be read.
 * @param {string|Date} value 'YYYY-MM-DD', an ISO timestamp (read as written) or a Date (read in the browser's zone).
 * @returns {{year: number, month: number, day: number, hour: number, minute: number, weekday: number}|null}
 */
export function wallClock(value) {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime())
            ? null
            : partsOf(value.getFullYear(), value.getMonth() + 1, value.getDate(), value.getHours(), value.getMinutes());
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/.exec(value ?? '');

    return match ? partsOf(...match.slice(1, 6).map((part) => Number(part ?? 0))) : null;
}

function partsOf(year, month, day, hour, minute) {
    return { year, month, day, hour, minute, weekday: new Date(Date.UTC(year, month - 1, day)).getUTCDay() };
}

/**
 * A clock reading: "3:15pm" or "15:15".
 * @param {string|Date} value See wallClock().
 * @param {string} [format] '12h' or '24h', the visitor's by default.
 * @returns {string|null}
 */
export function formatClock(value, format = formats.time()) {
    const at = wallClock(value);

    return at === null ? null : formatHourMinute(at.hour, at.minute, format);
}

/**
 * A clock reading from its parts, for callers that already hold them.
 * @param {number} hour 0-23.
 * @param {number} minute
 * @param {string} [format] '12h' or '24h', the visitor's by default.
 * @returns {string}
 */
export function formatHourMinute(hour, minute, format = formats.time()) {
    return format === '24h'
        ? `${pad(hour)}:${pad(minute)}`
        : `${hour % 12 || 12}:${pad(minute)}${hour < 12 ? 'am' : 'pm'}`;
}

/**
 * A calendar date, e.g. "Tue 22 Sep 2026", "22/09/2026" or "2026-09-22".
 * @param {string|Date} value See wallClock().
 * @param {{weekday?: boolean, year?: boolean}} [options] Lead the short format with the day name; include the year.
 * @param {string} [format] short, long, dmy, mdy or iso, the visitor's by default.
 * @returns {string|null}
 */
export function formatDate(value, { weekday = true, year = true } = {}, format = formats.date()) {
    const at = wallClock(value);

    if (at === null) {
        return null;
    }

    const [d, m] = [pad(at.day), pad(at.month)];

    switch (format) {
        case 'long':
            return `${at.day} ${MONTHS[at.month - 1]}${year ? ` ${at.year}` : ''}`;
        case 'dmy':
            return `${d}/${m}${year ? `/${at.year}` : ''}`;
        case 'mdy':
            return `${m}/${d}${year ? `/${at.year}` : ''}`;
        case 'iso':
            return `${year ? `${at.year}-` : ''}${m}-${d}`;
        default:
            return `${weekday ? `${WEEKDAYS[at.weekday]} ` : ''}${at.day} ${MONTHS[at.month - 1].slice(0, 3)}${year ? ` ${at.year}` : ''}`;
    }
}

/**
 * A date and clock reading together: "Tue 22 Sep 2026, 3:15pm".
 * @param {string|Date} value See wallClock().
 * @param {{weekday?: boolean}} [options] Lead the short format with the day name.
 * @returns {string|null}
 */
export function formatDateTime(value, { weekday = true } = {}) {
    const date = formatDate(value, { weekday });

    return date === null ? null : `${date}, ${formatClock(value)}`;
}

/**
 * A span of days, sharing its month and year where the format reads that way:
 * "2-4 Jun 2022", "2 Jun - 4 Jul 2022", "02/06/2022 - 04/06/2022".
 * @param {string|Date} start The first day.
 * @param {string|Date} end The last day.
 * @param {string} [format] As formatDate().
 * @returns {string|null}
 */
export function formatRange(start, end, format = formats.date()) {
    const [from, to] = [wallClock(start), wallClock(end)];

    if (from === null || to === null) {
        return null;
    }

    const last = formatDate(end, { weekday: false }, format);

    if (from.year === to.year && from.month === to.month && from.day === to.day) {
        return last;
    }

    if (['dmy', 'mdy', 'iso'].includes(format) || from.year !== to.year) {
        return `${formatDate(start, { weekday: false }, format)} - ${last}`;
    }

    if (from.month === to.month) {
        return `${from.day}-${last}`;
    }

    return `${formatDate(start, { weekday: false, year: false }, format)} - ${last}`;
}
