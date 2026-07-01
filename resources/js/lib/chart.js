// Chart.js, registered once and themed to the site's design tokens so story
// charts match the rest of the UI. Import { Chart, PALETTE, baseOptions } here
// rather than reaching for Chart.js directly.
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

Chart.defaults.font.family = "'Inter', -apple-system, system-ui, sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = '#6a6a6a';

// Design-system palette (resolved hexes; --color-fuel is hsl(45 85% 48%)).
export const PALETTE = {
    ink: '#222222',
    mid: '#6a6a6a',
    faint: '#dddddd',
    grid: 'rgba(34, 34, 34, 0.06)',
    fuel: '#e2ae12',
    fuelSoft: 'rgba(226, 174, 18, 0.16)',
    food: '#f37216',
    foodSoft: 'rgba(243, 114, 22, 0.16)',
    flight: '#209fdf',
    flightSoft: 'rgba(32, 159, 223, 0.16)',
    accent: '#3858e9',
    accentSoft: 'rgba(56, 88, 233, 0.12)',
};

// A dark tooltip matching the site's ink, with no colour swatch.
export const tooltip = {
    backgroundColor: '#222222',
    titleColor: '#ffffff',
    bodyColor: 'rgba(255, 255, 255, 0.78)',
    padding: 10,
    cornerRadius: 8,
    displayColors: false,
    titleFont: { weight: '600' },
};

/**
 * Shared chart options: fills the container height, hides the legend by default,
 * and applies the themed tooltip and quiet gridlines. Pass overrides for scales
 * or plugins as needed.
 */
export function baseOptions(overrides = {}) {
    const { plugins = {}, scales = {}, ...rest } = overrides;

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
            x: { grid: { display: false }, ticks: { color: PALETTE.mid }, ...(scales.x ?? {}) },
            y: { grid: { color: PALETTE.grid }, ticks: { color: PALETTE.mid }, border: { display: false }, ...(scales.y ?? {}) },
        },
        ...rest,
    };
}

export { Chart };
