<script setup>
import { computed } from 'vue';
import { unitTitle } from '../../lib/units.js';

const props = defineProps({
    value: { type: [String, Number], required: true },
    label: { type: String, required: true },
    unit: { type: String, default: null },
    // Optional trend series; drawn as a small inline sparkline under the number.
    spark: { type: Array, default: () => [] },
    // Accent hex for the sparkline stroke.
    accent: { type: String, default: 'currentColor' },
});

// Build an SVG polyline path from the series, normalised into a 100x28 box.
// Computed inline (no chart library) so a row of tiles stays cheap to render.
const sparkPath = computed(() => {
    if (props.spark.length < 2) {
        return null;
    }

    const min = Math.min(...props.spark);
    const max = Math.max(...props.spark);
    const span = max - min || 1;
    const step = 100 / (props.spark.length - 1);

    return props.spark
        .map((point, index) => `${index === 0 ? 'M' : 'L'}${(index * step).toFixed(1)},${(26 - ((point - min) / span) * 24).toFixed(1)}`)
        .join(' ');
});
</script>

<template>
    <div class="flex flex-col justify-between rounded-lg border border-neutral-50 bg-neutral-0 p-4">
        <dt class="text-label uppercase text-neutral-500">{{ label }}</dt>
        <dd class="mt-2 font-display text-stat leading-none tnum text-neutral-900">
            {{ value }}<abbr v-if="unit" :title="unitTitle(unit)" class="ml-1 text-base font-semibold text-neutral-500 no-underline">{{ unit }}</abbr>
        </dd>
        <svg v-if="sparkPath" class="mt-3 h-7 w-full" viewBox="0 0 100 28" preserveAspectRatio="none" aria-hidden="true">
            <path :d="sparkPath" fill="none" :stroke="accent" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
        </svg>
    </div>
</template>
