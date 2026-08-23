import { ref } from 'vue';
import { useTheme } from './useTheme';
import { readCookie, writeCookie } from './lib/cookies';

// Cookie namespace for factory-defined settings, so they never collide with the
// app's other keys (theme keeps its own bare 'theme' cookie, owned by useTheme
// + the pre-paint script, and is intentionally NOT routed through here).
const PREFIX = 'pref_';

// Module-level registry so each setting is a single shared reactive source
// across every consumer (idempotent per key).
const registry = {};

/**
 * What the server rendered with. Settings are defined at module scope, which is
 * too early to reach the Inertia page, and on the server there is no document
 * to read a cookie from. Both entrypoints seed this before creating the app so
 * the two sides agree on the first paint.
 */
let seeded = {};

export function seedPreferences(preferences) {
    seeded = preferences?.settings ?? {};

    Object.values(registry).forEach((setting) => setting.refresh());
}

/**
 * Define a reactive, cookie-backed preference. `allowed` whitelists valid
 * values; anything stored outside it falls back. Returns { value: Ref, set(v) }.
 */
export function defineSetting(key, fallback, allowed = null) {
    if (registry[key]) {
        return registry[key];
    }
    const cookieKey = PREFIX + key;
    const read = () => {
        const raw = readCookie(cookieKey) ?? seeded[key] ?? null;
        if (raw === null || (allowed && !allowed.includes(raw))) {
            return fallback;
        }
        return raw;
    };
    const value = ref(read());
    const set = (next) => {
        if (allowed && !allowed.includes(next)) {
            return;
        }
        value.value = next;
        writeCookie(cookieKey, next);
    };
    // A setting defined before the seed arrives would otherwise hold its
    // fallback forever.
    const refresh = () => (value.value = read());
    registry[key] = { value, set, refresh };
    return registry[key];
}

// Shared, module-level open-state so the desktop gear and the mobile-nav gear
// control the same single SettingsModal instance.
const settingsOpen = ref(false);

function openSettings() {
    settingsOpen.value = true;
}

function closeSettings() {
    settingsOpen.value = false;
}

// One settings surface: theme controls (from useTheme) plus the modal state.
// New enumerated settings are added via defineSetting (see above); theme stays
// on useTheme because it has extra behaviour (system resolution + pre-paint).
export function useSettings() {
    return { ...useTheme(), settingsOpen, openSettings, closeSettings };
}
