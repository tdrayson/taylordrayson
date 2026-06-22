<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    // Evenly-spaced BPM samples across the activity.
    data: { type: Array, required: true },
    duration: { type: Number, default: null },
    color: { type: String, default: 'var(--color-activity)' },
});

const container = ref(null);
const hoverIndex = ref(null);

const count = computed(() => props.data.length);

// Padded BPM domain so the trace never touches the top/bottom edges.
const domain = computed(() => {
    const lo = Math.max(40, Math.min(...props.data) - 8);
    const hi = Math.max(...props.data) + 8;

    return { lo, hi, span: hi - lo || 1 };
});

const average = computed(() => Math.round(props.data.reduce((sum, value) => sum + value, 0) / count.value));
const peak = computed(() => Math.max(...props.data));

function x(index) {
    return (index / (count.value - 1)) * 100;
}

function y(bpm) {
    return 95 - ((bpm - domain.value.lo) / domain.value.span) * 90;
}

const linePath = computed(() => props.data.map((bpm, index) => `${index === 0 ? 'M' : 'L'} ${x(index).toFixed(2)} ${y(bpm).toFixed(2)}`).join(' '));
const areaPath = computed(() => `${linePath.value} L 100 100 L 0 100 Z`);
const averageY = computed(() => y(average.value));

function clock(seconds) {
    const minutes = Math.floor(seconds / 60);

    return `${minutes}:${String(Math.round(seconds % 60)).padStart(2, '0')}`;
}

const hovered = computed(() => {
    if (hoverIndex.value === null) {
        return null;
    }

    const bpm = props.data[hoverIndex.value];

    return {
        bpm,
        left: x(hoverIndex.value),
        top: y(bpm),
        time: props.duration ? clock((hoverIndex.value / (count.value - 1)) * props.duration) : null,
    };
});

function onMove(event) {
    const rect = container.value.getBoundingClientRect();
    const ratio = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));

    hoverIndex.value = Math.round(ratio * (count.value - 1));
}
</script>

<template>
    <div
        ref="container"
        class="relative h-48 w-full touch-none select-none"
        @pointermove="onMove"
        @pointerleave="hoverIndex = null"
    >
        <svg class="absolute inset-0 size-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient :id="`hr-fill`" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="color" stop-opacity="0.22" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0" />
                </linearGradient>
            </defs>

            <line x1="0" :y1="averageY" x2="100" :y2="averageY" stroke="var(--color-ink-3)" stroke-width="1" stroke-dasharray="3 3" stroke-opacity="0.5" vector-effect="non-scaling-stroke" />
            <path :d="areaPath" :fill="`url(#hr-fill)`" />
            <path :d="linePath" fill="none" :stroke="color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />

            <line v-if="hovered" :x1="hovered.left" y1="0" :x2="hovered.left" y2="100" stroke="var(--color-ink-3)" stroke-width="1" stroke-opacity="0.4" vector-effect="non-scaling-stroke" />
        </svg>

        <div class="pointer-events-none absolute left-0 top-0 text-label uppercase text-ink-3 tnum">{{ peak }} peak</div>
        <div class="pointer-events-none absolute right-0 text-label uppercase text-ink-3 tnum" :style="{ top: `${averageY}%` }">avg {{ average }}</div>

        <template v-if="hovered">
            <div class="pointer-events-none absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-canvas" :style="{ left: `${hovered.left}%`, top: `${hovered.top}%`, background: color }" />
            <div
                class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-3 whitespace-nowrap rounded-md bg-ink px-2 py-1 text-xs font-medium text-canvas shadow-card tnum"
                :style="{ left: `${Math.min(90, Math.max(10, hovered.left))}%`, top: `${hovered.top}%` }"
            >
                {{ hovered.bpm }} bpm<template v-if="hovered.time"> · {{ hovered.time }}</template>
            </div>
        </template>
    </div>
</template>
