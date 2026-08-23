/**
 * Display preferences are stored in cookies, not local storage, so the server
 * can read them and render the right scheme and units first time.
 *
 * Every write is a plain first-party cookie holding a value the visitor chose
 * through the settings panel: no identifier, no third party, nothing tracked.
 */

/** A year, so a preference survives between visits. */
const MAX_AGE = 31536000;

/** Read a cookie, or null when it is unset or we are not in a browser. */
export function readCookie(name) {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

/** Persist a preference for a year. Lax so it still arrives on a normal visit. */
export function writeCookie(name, value) {
    if (typeof document === 'undefined') {
        return;
    }

    document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=${MAX_AGE};samesite=lax`;
}
