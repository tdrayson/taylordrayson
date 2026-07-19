// Chart.js, registered once and themed to the site's design tokens so story
// charts match the rest of the UI. Import { Chart, PALETTE, baseOptions } here
// rather than reaching for Chart.js directly.
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

Chart.defaults.font.family = "'Inter', -apple-system, system-ui, sans-serif";
Chart.defaults.font.size = 11;

// Read a design token's current computed value off :root. Values flip when
// the `.dark` class toggles, so calling this live (rather than caching the
// result) is what makes charts follow the active theme.
function token(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

// Build the chart palette from live tokens so charts match light/dark.
export function getPalette() {
    return {
        ink: token('--color-neutral-900'),
        mid: token('--color-neutral-500'),
        faint: token('--color-neutral-100'),
        grid: `color-mix(in srgb, ${token('--color-neutral-900')} 8%, transparent)`,
        fuel: token('--color-fuel'),
        fuelSoft: `color-mix(in srgb, ${token('--color-fuel')} 16%, transparent)`,
        food: token('--color-food'),
        foodSoft: `color-mix(in srgb, ${token('--color-food')} 16%, transparent)`,
        flight: token('--color-flight'),
        flightSoft: `color-mix(in srgb, ${token('--color-flight')} 16%, transparent)`,
        accent: token('--color-accent-500'),
        accentSoft: `color-mix(in srgb, ${token('--color-accent-500')} 12%, transparent)`,
    };
}

// Backward-compatible palette object: story pages (Stats/Food/Fuel/Flights)
// import PALETTE.<key> directly for dataset colours. Each property is a
// getter that re-reads getPalette() on access, so it resolves to whichever
// theme is active when the consuming page renders, without those pages
// needing to know about tokens themselves.
export const PALETTE = {
    get ink() { return getPalette().ink; },
    get mid() { return getPalette().mid; },
    get faint() { return getPalette().faint; },
    get grid() { return getPalette().grid; },
    get fuel() { return getPalette().fuel; },
    get fuelSoft() { return getPalette().fuelSoft; },
    get food() { return getPalette().food; },
    get foodSoft() { return getPalette().foodSoft; },
    get flight() { return getPalette().flight; },
    get flightSoft() { return getPalette().flightSoft; },
    get accent() { return getPalette().accent; },
    get accentSoft() { return getPalette().accentSoft; },
};

// A themed tooltip: solid ink background with inverted (light-on-dark /
// dark-on-light) text so it stays legible in both themes. Colours are
// scriptable functions rather than plain strings — Chart.js resolves these
// at draw time, which is what lets Chart.vue's destroy/recreate-on-theme-
// change actually repaint the tooltip instead of reusing stale strings baked
// into a chart's options object at mount time.
export const tooltip = {
    backgroundColor: () => token('--color-neutral-900'),
    titleColor: () => token('--color-neutral-0'),
    bodyColor: () => `color-mix(in srgb, ${token('--color-neutral-0')} 78%, transparent)`,
    padding: 10,
    cornerRadius: 8,
    displayColors: false,
    titleFont: { weight: '600' },
};

/**
 * Shared chart options: fills the container height, hides the legend by default,
 * and applies the themed tooltip and quiet gridlines. Pass overrides for scales
 * or plugins as needed.
 *
 * Axis tick/grid colours are scriptable functions (not plain strings) for the
 * same reason as the tooltip above: Chart.js re-resolves them on every
 * render, so a chart rebuilt after a theme change picks up the new palette
 * even though the surrounding options object it was constructed with is
 * otherwise unchanged.
 */
export function baseOptions(overrides = {}) {
    const { plugins = {}, scales = {}, ...rest } = overrides;

    // Refresh the global default text colour so any chart built after this
    // call falls back to the currently active theme.
    Chart.defaults.color = getPalette().mid;

    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600 },
        // Hovering anywhere in a column surfaces that point, so the whole chart
        // feels interactive rather than only the exact markers.
        interaction: { mode: 'index', intersect: false },
        hover: { mode: 'index', intersect: false },
        elements: { point: { hoverRadius: 6, hoverBorderWidth: 2 } },
        plugins: {
            legend: { display: false },
            tooltip,
            ...plugins,
        },
        scales: {
            x: {
                grid: { display: false },
                ...(scales.x ?? {}),
                // Merge the caller's ticks on top of the defaults, but force the
                // scriptable colour last so it always re-reads the live theme
                // instead of being clobbered by a caller's static ticks object.
                ticks: { ...(scales.x?.ticks ?? {}), color: () => getPalette().mid },
            },
            y: {
                grid: { color: () => getPalette().grid },
                border: { display: false },
                ...(scales.y ?? {}),
                ticks: { ...(scales.y?.ticks ?? {}), color: () => getPalette().mid },
            },
        },
        ...rest,
    };
}

export { Chart };
