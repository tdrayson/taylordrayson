import { ref, computed } from 'vue';

const STORAGE_KEY = 'theme';
const media = window.matchMedia('(prefers-color-scheme: dark)');

// Shared singleton state so every consumer (toggle, charts) sees the same value.
const theme = ref(readStored());
const systemDark = ref(media.matches);

// The stored preference; 'system' when unset or invalid.
function readStored() {
    const v = localStorage.getItem(STORAGE_KEY);
    return v === 'light' || v === 'dark' || v === 'system' ? v : 'system';
}

// The effective scheme after resolving 'system' against the OS setting.
const resolved = computed(() =>
    theme.value === 'system' ? (systemDark.value ? 'dark' : 'light') : theme.value,
);

// Reflect the resolved scheme onto <html> so the CSS .dark scope activates.
function applyClass() {
    document.documentElement.classList.toggle('dark', resolved.value === 'dark');
}

// Keep the resolved scheme in sync when the OS theme flips (only matters in 'system').
media.addEventListener('change', (e) => {
    systemDark.value = e.matches;
    applyClass();
});

// Persist a new preference and update the DOM immediately.
function setTheme(value) {
    theme.value = value;
    localStorage.setItem(STORAGE_KEY, value);
    applyClass();
}

// Called once at startup to align the class with stored state after hydration.
function applyTheme() {
    theme.value = readStored();
    systemDark.value = media.matches;
    applyClass();
}

export function useTheme() {
    return { theme, resolved, setTheme };
}

export { applyTheme };
