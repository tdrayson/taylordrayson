/**
 * Carrying unsaved values into the editor about to open, e.g. a note grown into
 * an article or a duplicated entry. Nothing is saved on the way across.
 */
const KEY = 'entry-handoff';

/** Session storage, or null where there is none (server-side render). */
function store() {
    return typeof sessionStorage === 'undefined' ? null : sessionStorage;
}

/** Hold values for the type about to be opened. */
export function stash(type, values) {
    store()?.setItem(KEY, JSON.stringify({ type, values }));
}

/**
 * Take back anything held for this type, clearing it either way so opening the
 * same editor again later starts empty.
 */
export function claim(type) {
    const held = store();

    if (! held) {
        return {};
    }

    const raw = held.getItem(KEY);
    held.removeItem(KEY);

    try {
        const parsed = JSON.parse(raw);

        return parsed?.type === type ? parsed.values ?? {} : {};
    } catch {
        return {};
    }
}
