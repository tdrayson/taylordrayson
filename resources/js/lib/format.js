/**
 * Formatting helpers shared across entry detail components.
 */

export function duration(seconds) {
    if (seconds === null || seconds === undefined) {
        return null;
    }

    const total = Math.round(seconds);
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);

    if (hours > 0) {
        return `${hours}h ${String(minutes).padStart(2, '0')}m`;
    }

    if (minutes > 0) {
        return `${minutes}m`;
    }

    return `${total}s`;
}

/**
 * Rough en-route time estimated from great-circle distance: ~500 mph cruise plus
 * 25 minutes for taxi, climb and descent. Used where exact block time is unknown.
 */
export function flightDurationLabel(miles) {
    if (!miles) {
        return null;
    }

    const minutes = Math.round((Number(miles) / 500) * 60 + 25);

    return duration(minutes * 60);
}

/** Full date without time: Monday, 23 March 2026. */
export function dateLong(value) {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/** Compact date for a dense list, e.g. "17 Jul 2025". */
export function dateShort(value) {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

/**
 * Full date without time from a date-only 'YYYY-MM-DD' string, e.g. a watch-date
 * group anchor. Parses the parts manually rather than `new Date('YYYY-MM-DD')`,
 * which JS treats as UTC midnight and can render as the PREVIOUS day once
 * converted to a negative-UTC-offset local time.
 */
export function dateLongFromYmd(value) {
    if (!value) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/**
 * Local midnight on the day a value falls on, or null if it cannot be read.
 * A bare 'YYYY-MM-DD' is a wall-clock date and is taken as written; a full
 * timestamp is an instant, so the calendar day the reader is in is the one
 * that decides whether it was "yesterday".
 */
function startOfDay(value) {
    const bare = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (bare) {
        return new Date(Number(bare[1]), Number(bare[2]) - 1, Number(bare[3]));
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime())
        ? null
        : new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
}

/**
 * Relative day label for a 'YYYY-MM-DD' date or an ISO timestamp, or null once
 * it is older than the cutoff so the caller falls back to its absolute date.
 * The one relative-date rule on the site; every surface reads from here.
 *
 * Counted in calendar days rather than elapsed hours: something logged at 11pm
 * yesterday reads as "Yesterday" at 1am, not "Today". Rounding absorbs the 23
 * and 25 hour days either side of a DST change.
 */
export function relativeDay(value, cutoffDays = 14) {
    if (!value) {
        return null;
    }

    const then = startOfDay(value);

    if (then === null) {
        return null;
    }

    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const days = Math.round((today - then) / 86400000);

    // Future dates fall through to the absolute label rather than reading as
    // "-2 days ago".
    if (days < 0 || days > cutoffDays) {
        return null;
    }

    if (days === 0) {
        return 'Today';
    }

    if (days === 1) {
        return 'Yesterday';
    }

    return `${days} days ago`;
}

/** Canonical clock format used everywhere: 6:55am, 9:00pm, 12:30pm. */
export function clock(date) {
    const hours = date.getHours();
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const suffix = hours < 12 ? 'am' : 'pm';

    return `${hours % 12 || 12}:${minutes}${suffix}`;
}

export function number(value, fractionDigits = 0) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return Number(value).toLocaleString('en-GB', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    });
}

/**
 * GBP money, always to two decimal places: 45.6 becomes "£45.60", 45 becomes
 * "£45.00". Returns null for blank values so callers can drop empty stats.
 * (Per-litre fuel price is the one deliberate exception - it stays at three
 * decimals via number(value, 3), matching how pumps price fuel.)
 */
export function money(value) {
    const formatted = number(value, 2);

    return formatted === null ? null : `£${formatted}`;
}

// Kilograms to pounds. Returns the unrounded value; the formatter applies
// display rounding via number(), matching how raw kg is rounded at render.
export function kgToLbs(kg) {
    return Number(kg) * 2.20462;
}

export function dateTime(value) {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const day = date.toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return `${day}, ${clock(date)}`;
}

export function time(value) {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return clock(date);
}

export function titleCase(value) {
    if (!value) {
        return '';
    }

    return String(value)
        .toLowerCase()
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}
