import { ref, computed } from 'vue';
import { readCookie, writeCookie } from './lib/cookies';

const THEME_COOKIE = 'theme';
const SCHEME_COOKIE = 'scheme';

// Nothing here may touch `window` while the module loads: under SSR this file
// is imported on the server, where there is no matchMedia and no document.
const media = typeof window === 'undefined'
    ? null
    : window.matchMedia('(prefers-color-scheme: dark)');

// Shared singleton state so every consumer (toggle, charts) sees the same value.
const theme = ref('system');
const systemDark = ref(false);

// The effective scheme after resolving 'system' against the OS setting.
const resolved = computed(() =>
    theme.value === 'system' ? (systemDark.value ? 'dark' : 'light') : theme.value,
);

// Reflect the resolved scheme onto <html> so the CSS .dark scope activates.
// The server sets the same class from the cookie, so this only corrects it.
function applyClass() {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle('dark', resolved.value === 'dark');
    // Tell the server what 'system' resolved to, so the next request renders
    // the right class rather than guessing light.
    writeCookie(SCHEME_COOKIE, resolved.value);
}

media?.addEventListener('change', (event) => {
    systemDark.value = event.matches;
    applyClass();
});

// Persist a new preference and update the DOM immediately.
function setTheme(value) {
    theme.value = value;
    writeCookie(THEME_COOKIE, value);
    applyClass();
}

/**
 * Align the store with what the server rendered. Takes the preferences rather
 * than reaching for the Inertia page, which does not exist yet when this runs.
 */
function applyTheme(preferences = {}) {
    theme.value = readCookie(THEME_COOKIE) ?? preferences.theme ?? 'system';
    systemDark.value = media ? media.matches : preferences.scheme === 'dark';
    applyClass();
}

export function useTheme() {
    return { theme, resolved, setTheme };
}

export { applyTheme };
