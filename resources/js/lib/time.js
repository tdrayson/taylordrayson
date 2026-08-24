/**
 * One clock for the whole app, read in a named timezone rather than whichever
 * one the browser happens to be in. The site reports my day, so a reader
 * elsewhere should see my time, not theirs.
 *
 * Everything here formats an explicit `timeZone`, which also keeps the server
 * and the browser in agreement: neither is reading a local clock.
 */

/** Where the site is, for when the phone has never reported a zone. */
export const DEFAULT_TIMEZONE = 'Europe/London';

/** The hour (0-23) and minute an instant reads as in a timezone. */
export function clockParts(timezone, at = new Date()) {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: timezone,
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(at);

    const value = (type) => Number(parts.find((part) => part.type === type)?.value ?? 0);

    // hour12: false renders midnight as 24 rather than 0.
    return { hour: value('hour') % 24, minute: value('minute') };
}

/** The time as the status bar writes it, e.g. `9:05am`. */
export function formatTime(timezone, at = new Date()) {
    return new Intl.DateTimeFormat('en-GB', {
        timeZone: timezone,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
        .format(at)
        .replace(/\s+/g, '')
        .toLowerCase();
}

/** The date as the status bar's tooltip writes it, e.g. `Sunday 24 August 2026`. */
export function formatDate(timezone, at = new Date()) {
    return new Intl.DateTimeFormat('en-GB', {
        timeZone: timezone,
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(at);
}

/**
 * Minutes east of UTC, negative for west.
 *
 * Read from the formatter rather than by differencing two parsed dates, so a
 * half-hour zone and a DST switch both come out right.
 */
export function offsetMinutes(timezone, at = new Date()) {
    const name = new Intl.DateTimeFormat('en-GB', {
        timeZone: timezone,
        timeZoneName: 'longOffset',
    })
        .formatToParts(at)
        .find((part) => part.type === 'timeZoneName')?.value ?? '';

    const match = name.match(/GMT([+-])(\d{2}):(\d{2})/);

    // UTC itself formats as a bare "GMT", with no offset to match.
    if (! match) {
        return 0;
    }

    return (match[1] === '-' ? -1 : 1) * (Number(match[2]) * 60 + Number(match[3]));
}
