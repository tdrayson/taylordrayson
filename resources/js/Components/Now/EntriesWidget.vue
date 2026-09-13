<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Tooltip from '../Ui/Tooltip.vue';

const props = defineProps({
    label: { type: String, default: 'entries' },
    // Four Mon-Sun weeks of { date: 'yyyy-mm-dd', count }, oldest first; days after today have a null count.
    days: {
        type: Array,
        default: () => [3, 5, 0, 2, 6, 4, 1, 0, 3, 7, 5, 2, 4, 1, 0, 6, 3, 5, 8, 2, 1, 4, 0, 3, 5, 6, null, null].map((count, i) => ({
            date: new Date(Date.UTC(2026, 7, 17 + i)).toISOString().slice(0, 10),
            count,
        })),
    },
});

const WEEKDAYS = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

// Empty + three accent steps.
const COLORS = ['var(--color-neutral-100)', 'var(--color-accent-200)', 'var(--color-accent-400)', 'var(--color-accent-600)'];
const level = (c) => (c === 0 ? 0 : c <= 2 ? 1 : c <= 4 ? 2 : 3);

const total = computed(() => props.days.reduce((sum, day) => sum + (day.count ?? 0), 0));

function fmtCount(c) {
    if (c === 0) {
        return 'No entries';
    }
    return c === 1 ? '1 entry' : `${c} entries`;
}

// The server sends plain dates, so format them in UTC to avoid any local timezone shift.
const cells = computed(() => props.days.map(({ date, count }) => {
    if (count === null) {
        return { key: date, future: true };
    }

    const [year, month, day] = date.split('-');
    const label = new Date(Date.UTC(year, month - 1, day))
        .toLocaleDateString('en-GB', { timeZone: 'UTC', weekday: 'short', day: 'numeric', month: 'short' });

    return {
        key: date,
        href: `/${year}/${month}/${day}`,
        color: COLORS[level(count)],
        title: `${label}, ${fmtCount(count)}`,
    };
}));
</script>

<template>
    <div class="entries rounded-3xl">
        <div class="entries__inner">
            <div class="entries__header">
                <h2 class="entries__title">Last 4 weeks</h2>
                <span class="entries__total">{{ total }}</span>
            </div>

            <div class="entries__grid">
                <span v-for="(weekday, i) in WEEKDAYS" :key="`weekday-${i}`" class="entries__weekday" aria-hidden="true">{{ weekday }}</span>
                <template v-for="cell in cells" :key="cell.key">
                    <span v-if="cell.future" class="entries__cell-wrap entries__cell--future" />
                    <Tooltip v-else :label="cell.title" placement="top" class="entries__cell-wrap">
                        <Link :href="cell.href" class="entries__cell" :style="{ background: cell.color }" :aria-label="cell.title" />
                    </Tooltip>
                </template>
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
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    align-content: center;
    gap: 2.3cqw;
    margin: 5.6cqw 0 5.1cqw;
}

.entries__weekday {
    font-size: 3.7cqw;
    font-weight: 600;
    line-height: 1;
    text-align: center;
    color: var(--color-neutral-400);
}

.entries__cell-wrap {
    display: flex;
    width: 100%;
    aspect-ratio: 1;
}

.entries__cell--future {
    border-radius: 1.85cqw;
    box-shadow: inset 0 0 0 1px var(--color-neutral-100);
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
