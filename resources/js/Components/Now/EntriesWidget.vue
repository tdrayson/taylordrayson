<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Tooltip from '../Ui/Tooltip.vue';

const props = defineProps({
    label: { type: String, default: 'entries' },
    // Per-day entry counts, oldest first, most recent (today) last.
    counts: {
        type: Array,
        default: () => [3, 5, 0, 2, 6, 4, 1, 0, 3, 7, 5, 2, 4, 1, 0, 6, 3, 5, 8, 2, 1, 4, 0, 3, 5, 6, 2, 4, 7, 3],
    },
});

// Empty + three accent steps.
const COLORS = ['var(--color-neutral-100)', 'var(--color-accent-200)', 'var(--color-accent-400)', 'var(--color-accent-600)'];
const level = (c) => (c === 0 ? 0 : c <= 2 ? 1 : c <= 4 ? 2 : 3);

const total = computed(() => props.counts.reduce((a, b) => a + b, 0));

function fmtCount(c) {
    if (c === 0) {
        return 'No entries';
    }
    return c === 1 ? '1 entry' : `${c} entries`;
}

const cells = computed(() => {
    const today = new Date(new Date().toLocaleString('en-US', { timeZone: 'Europe/London' }));
    today.setHours(0, 0, 0, 0);

    return props.counts.map((c, i) => {
        const d = new Date(today);
        d.setDate(d.getDate() - (props.counts.length - 1 - i));
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const date = d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });

        return {
            href: `/${year}/${month}/${day}`,
            color: COLORS[level(c)],
            title: `${date}, ${fmtCount(c)}`,
        };
    });
});
</script>

<template>
    <div class="entries rounded-3xl">
        <div class="entries__inner">
            <div class="entries__header">
                <h2 class="entries__title">Last 30 days</h2>
                <span class="entries__total">{{ total }}</span>
            </div>

            <div class="entries__grid">
                <Tooltip v-for="cell in cells" :key="cell.href" :label="cell.title" placement="top" class="entries__cell-wrap">
                    <Link :href="cell.href" class="entries__cell" :style="{ background: cell.color }" :aria-label="cell.title" />
                </Tooltip>
            </div>

            <div class="entries__footer">
                <span class="entries__footer-label">{{ label }}</span>
                <span class="entries__legend">
                    <i v-for="color in COLORS" :key="color" class="entries__legend-swatch" :style="{ background: color }" />
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* The card is the query container; inner sizing is in cqw (1cqw ≈ reference
   px ÷ 2.16). Padding/flex live on .entries__inner so cqw references the card. */
.entries {
    container-type: inline-size;
    aspect-ratio: 1 / 1;
    background: var(--color-neutral-0);
    box-shadow: var(--shadow-card);
    color: var(--color-neutral-900);
}

.entries__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    padding: 8.3cqw;
}

.entries__header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
}

.entries__title {
    font-size: 6cqw;
    font-weight: 800;
    letter-spacing: -0.01em;
}

.entries__total {
    font-size: 6.5cqw;
    font-weight: 800;
    color: var(--color-accent-500);
}

.entries__grid {
    flex: 1;
    min-height: 0;
    align-self: center;
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    grid-template-rows: repeat(5, 1fr);
    gap: 2.3cqw;
    margin: 5.6cqw 0 5.1cqw;
    /* Fill the available height, then derive width so the 6×5 cells stay square. */
    aspect-ratio: 6 / 5;
}

.entries__cell-wrap {
    display: flex;
    width: 100%;
    height: 100%;
}

.entries__cell {
    flex: 1;
    align-self: stretch;
    border-radius: 1.85cqw;
    transition: transform 0.12s ease;
}

.entries__cell:hover {
    transform: scale(1.18);
    z-index: 1;
}

.entries__cell:focus-visible {
    outline: 2px solid var(--color-accent-500);
    outline-offset: 2px;
    z-index: 1;
}

.entries__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.entries__footer-label {
    font-size: 5.1cqw;
    font-weight: 600;
    color: var(--color-neutral-500);
}

.entries__legend {
    display: flex;
    align-items: center;
    gap: 1.4cqw;
}

.entries__legend-swatch {
    width: 3.7cqw;
    height: 3.7cqw;
    border-radius: 1.16cqw;
}
</style>
