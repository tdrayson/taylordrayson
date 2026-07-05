<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    // Real data mode: entries-per-day keyed yyyy-mm-dd, for `year`.
    days: { type: Object, default: null },
    year: { type: Number, default: null },
});

const ramp = ['var(--color-neutral-25)', 'var(--color-heat-1)', 'var(--color-heat-2)', 'var(--color-heat-3)', 'var(--color-heat-4)'];

// GitHub-style buckets: 0, 1-2, 3-5, 6-9, 10+.
function bucket(count) {
    if (count <= 0) return 0;
    if (count <= 2) return 1;
    if (count <= 5) return 2;
    if (count <= 9) return 3;
    return 4;
}

const pad = (value) => String(value).padStart(2, '0');

// Real-data cells: leading nulls pad the first week so columns are true
// Mon-Sun weeks; each cell carries its date, count and day-page href.
const yearCells = computed(() => {
    if (!props.days || !props.year) return null;

    const cells = [];
    const first = new Date(props.year, 0, 1);
    const offset = (first.getDay() + 6) % 7;

    for (let i = 0; i < offset; i++) cells.push(null);

    const date = new Date(props.year, 0, 1);
    while (date.getFullYear() === props.year) {
        const key = `${props.year}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        const count = props.days[key] ?? 0;
        cells.push({
            key,
            href: `/${props.year}/${pad(date.getMonth() + 1)}/${pad(date.getDate())}`,
            title: `${date.getDate()} ${date.toLocaleDateString('en-GB', { month: 'short' })} · ${count} ${count === 1 ? 'entry' : 'entries'}`,
            level: bucket(count),
        });
        date.setDate(date.getDate() + 1);
    }

    return cells;
});

// Month labels positioned by the week column their 1st falls into.
const monthLabels = computed(() => {
    if (!props.year) return [];

    const first = new Date(props.year, 0, 1);
    const offset = (first.getDay() + 6) % 7;

    return Array.from({ length: 12 }, (_, month) => {
        const start = new Date(props.year, month, 1);
        const dayOfYear = Math.round((start - first) / 86400000);
        return {
            label: start.toLocaleDateString('en-GB', { month: 'short' }),
            href: `/${props.year}/${pad(month + 1)}`,
            column: Math.floor((offset + dayOfYear) / 7) + 1,
        };
    });
});

// Fallback: the original procedural texture for hosts without data (/now).
const fallbackCells = Array.from({ length: 364 }, (_, i) => {
    const raw = (Math.sin((i + 1) * 43.13) * 4313.13) % 1;
    const value = raw < 0 ? raw + 1 : raw;

    return value < 0.18 ? 0 : value < 0.42 ? 1 : value < 0.68 ? 2 : value < 0.88 ? 3 : 4;
});

function color(level) {
    return ramp[level];
}
</script>

<template>
    <div>
        <!-- Month labels double as year → month navigation. -->
        <div v-if="yearCells" class="heatmap-months mb-1 text-xs text-neutral-500">
            <Link
                v-for="month in monthLabels"
                :key="month.href"
                :href="month.href"
                :style="{ gridColumnStart: month.column }"
                class="rounded-sm underline-offset-2 transition-colors hover:text-accent-500 hover:underline focus-visible:text-accent-500 focus-visible:underline focus-visible:outline-none"
            >{{ month.label }}</Link>
        </div>

        <div v-if="yearCells" class="heatmap-grid">
            <template v-for="(cell, index) in yearCells" :key="cell ? cell.key : `pad-${index}`">
                <Link
                    v-if="cell"
                    :href="cell.href"
                    :title="cell.title"
                    :aria-label="cell.title"
                    class="rounded focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-accent-500"
                    :style="{ background: color(cell.level) }"
                />
                <span v-else />
            </template>
        </div>
        <div v-else class="heatmap-grid">
            <span v-for="(level, index) in fallbackCells" :key="index" class="rounded" :style="{ background: color(level) }" />
        </div>

        <div class="mt-3 flex items-center gap-1.5 text-xs text-neutral-500">
            Quieter
            <span v-for="(swatch, index) in ramp" :key="index" class="inline-block size-3 rounded" :style="{ background: swatch }" />
            Busier
        </div>
    </div>
</template>

<style scoped>
/* Cells flow column-major into 7 weekday rows → ~53 week columns.
   1fr columns stretch the grid to the container width; aspect-ratio keeps cells square. */
.heatmap-grid {
    display: grid;
    grid-auto-flow: column;
    grid-template-rows: repeat(7, 1fr);
    grid-auto-columns: 1fr;
    gap: 2px;
}

.heatmap-grid > * {
    aspect-ratio: 1;
}

/* Same column geometry as the grid so labels sit over their month's first week. */
.heatmap-months {
    display: grid;
    grid-auto-columns: 1fr;
    grid-auto-flow: column;
    grid-template-columns: repeat(53, 1fr);
}
</style>
