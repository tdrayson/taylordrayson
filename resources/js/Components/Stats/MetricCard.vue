<script setup>
import { computed } from 'vue';
import { unitTitle } from '../../lib/units.js';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    value: { type: [String, Number], default: null },
    label: { type: String, required: true },
    unit: { type: String, default: null },
    // Raw metres; when set, the value/unit are derived from this via the unit
    // toggle instead of the static value/unit props above.
    distanceM: { type: Number, default: null },
    precision: { type: Number, default: 0 },
    // Percent change vs the previous period; null hides the delta chip.
    delta: { type: Number, default: null },
    // The metric's own series over the range, so the sparkline is meaningful.
    spark: { type: Array, default: () => [] },
    accent: { type: String, default: 'currentColor' },
});

const { distanceParts } = useFormat();

// When given raw metres, format through the unit toggle; else use the passed value/unit.
const display = computed(() => {
    if (props.distanceM !== null) {
        return distanceParts(props.distanceM, props.precision);
    }
    return { value: props.value, unit: props.unit };
});

// SVG polyline path from the series, normalised into a 100x24 box.
const sparkPath = computed(() => {
    if (props.spark.length < 2) {
        return null;
    }

    const min = Math.min(...props.spark);
    const max = Math.max(...props.spark);
    const span = max - min || 1;
    const step = 100 / (props.spark.length - 1);

    return props.spark
        .map((point, index) => `${index === 0 ? 'M' : 'L'}${(index * step).toFixed(1)},${(22 - ((point - min) / span) * 20).toFixed(1)}`)
        .join(' ');
});

const up = computed(() => (props.delta ?? 0) >= 0);
</script>

<template>
    <div class="flex flex-col gap-3 rounded-lg border border-neutral-50 bg-neutral-0 p-4">
        <div class="flex items-center justify-between gap-2">
            <dt class="text-label uppercase text-neutral-500">{{ label }}</dt>
            <!-- Comparison vs the previous period: accent for up, muted for down. -->
            <span
                v-if="delta !== null"
                class="text-label font-semibold tnum"
                :style="up ? { color: accent } : null"
                :class="up ? '' : 'text-neutral-400'"
            >{{ up ? '↑' : '↓' }} {{ Math.abs(delta) }}%</span>
        </div>
        <dd class="font-display text-stat leading-none tnum text-neutral-900">
            {{ display.value }}<abbr v-if="display.unit" :title="unitTitle(display.unit)" class="ml-1 text-base font-semibold text-neutral-500 no-underline">{{ display.unit }}</abbr>
        </dd>
        <svg v-if="sparkPath" class="h-6 w-full" viewBox="0 0 100 24" preserveAspectRatio="none" aria-hidden="true">
            <path :d="sparkPath" fill="none" :stroke="accent" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
        </svg>
    </div>
</template>
