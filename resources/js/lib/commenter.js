/**
 * Who last commented from this browser, so a returning visitor does not retype
 * their name every time.
 *
 * localStorage rather than the cookie-backed settings store: an email address
 * has no business riding along on every request, and defineSetting is for
 * enumerated values (mi/km) rather than free text.
 *
 * Deliberately does not remember the reply-notification choice. That is an
 * opt-in to being emailed, and an opt-in restored from storage is not one.
 */
const KEY = 'commenter';

/** @return {{name: string, email: string}|null} */
export function readCommenter() {
    try {
        const stored = JSON.parse(localStorage.getItem(KEY) ?? 'null');

        return stored && typeof stored.name === 'string'
            ? { name: stored.name, email: typeof stored.email === 'string' ? stored.email : '' }
            : null;
    } catch {
        // Private windows and blocked site data throw on access rather than
        // returning null, so every path here has to survive it.
        return null;
    }
}

export function rememberCommenter(name, email) {
    try {
        localStorage.setItem(KEY, JSON.stringify({ name, email: email || '' }));
    } catch {
        // Not remembering somebody is a worse form but not a broken one.
    }
}
