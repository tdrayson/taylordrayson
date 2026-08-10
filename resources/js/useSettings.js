import { ref } from 'vue';
import { useTheme } from './useTheme';

// localStorage namespace for factory-defined settings, so they never collide
// with the app's other keys (theme keeps its own bare 'theme' key, owned by
// useTheme + the pre-paint script, and is intentionally NOT routed through here).
const PREFIX = 'pref:';

// Module-level registry so each setting is a single shared reactive source
// across every consumer (idempotent per key).
const registry = {};

/**
 * Define a reactive, localStorage-backed preference. `allowed` whitelists valid
 * values; anything stored outside it falls back. Returns { value: Ref, set(v) }.
 */
export function defineSetting(key, fallback, allowed = null) {
    if (registry[key]) {
        return registry[key];
    }
    const storageKey = PREFIX + key;
    const read = () => {
        const raw = localStorage.getItem(storageKey);
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
        localStorage.setItem(storageKey, next);
    };
    registry[key] = { value, set };
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
