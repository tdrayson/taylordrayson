/**
 * This browser's side of the reactions it has left: a random token the server
 * keys them on, and which emoji it picked on which entry.
 *
 * The server never learns who anybody is. It stores the token hashed with the
 * entry, so its rows cannot be joined across entries, and it cannot say which
 * reactions are yours on a page load. That part lives here.
 */
const KEY = 'reactor';

/** @return {{token: string, picks: Object<string, string>}} */
function read() {
    try {
        const stored = JSON.parse(localStorage.getItem(KEY) ?? 'null');

        if (stored && typeof stored.token === 'string') {
            return { token: stored.token, picks: stored.picks ?? {} };
        }
    } catch {
        // Private windows and blocked site data throw on access.
    }

    return { token: crypto.randomUUID(), picks: {} };
}

function write(state) {
    try {
        localStorage.setItem(KEY, JSON.stringify(state));
    } catch {
        // Unsaved, the reaction still counts; it just is not highlighted next visit.
    }
}

/**
 * The token to react with, minted on first use.
 * @return {string}
 */
export function reactorToken() {
    const state = read();

    write(state);

    return state.token;
}

/**
 * Which emoji this browser picked on an entry.
 * @param {string} target Keyed `type:id`.
 * @return {string|null}
 */
export function pickOn(target) {
    return read().picks[target] ?? null;
}

/**
 * Record the server's answer to a click.
 * @param {string} target Keyed `type:id`.
 * @param {string|null} key The emoji now picked, or null once taken back.
 */
export function rememberPick(target, key) {
    const state = read();

    if (key) {
        state.picks[target] = key;
    } else {
        delete state.picks[target];
    }

    write(state);
}
