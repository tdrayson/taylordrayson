<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    // Evenly-spaced numeric samples across the activity (already in display units).
    points: { type: Array, required: true },
    duration: { type: Number, default: null },
    color: { type: String, default: 'var(--color-activity)' },
    unit: { type: String, default: '' },
    // Shared cursor (useActivityCursor): index ref + set/clear.
    cursor: { type: Object, required: true },
    fill: { type: Boolean, default: true },
});

const container = ref(null);
const count = computed(() => props.points.length);
const TOP = 16;

// Padded value domain so the trace never touches the top/bottom edges.
const domain = computed(() => {
    const lo = Math.min(...props.points);
    const hi = Math.max(...props.points);
    const pad = (hi - lo) * 0.1 || 1;

    return { lo: lo - pad, hi: hi + pad, span: (hi - lo) + 2 * pad || 1 };
});

// SVG x for a point index and y for a value (viewBox 0..100).
function x(index) {
    return count.value <= 1 ? 0 : (index / (count.value - 1)) * 100;
}
function y(value) {
    return 95 - ((value - domain.value.lo) / domain.value.span) * (95 - TOP);
}

const linePath = computed(() => props.points.map((value, index) => `${index === 0 ? 'M' : 'L'} ${x(index).toFixed(2)} ${y(value).toFixed(2)}`).join(' '));
const areaPath = computed(() => `${linePath.value} L 100 100 L 0 100 Z`);

function clock(seconds) {
    const minutes = Math.floor(seconds / 60);
    return `${minutes}:${String(Math.round(seconds % 60)).padStart(2, '0')}`;
}

// The hovered point derived from the SHARED cursor fraction (null when inactive).
// The 0..1 fraction is mapped to THIS chart's own index, so charts of different
// lengths stay in sync by position along the activity.
const hovered = computed(() => {
    const fraction = props.cursor.fraction.value;

    if (fraction == null || count.value === 0) {
        return null;
    }

    const index = Math.round(fraction * (count.value - 1));
    const value = props.points[index];

    return {
        value,
        left: x(index),
        top: y(value),
        time: props.duration ? clock(fraction * props.duration) : null,
    };
});

// Map the pointer's x within the chart to a 0..1 fraction shared via the cursor.
function onMove(event) {
    const rect = container.value.getBoundingClientRect();
    props.cursor.set(Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)));
}

/**
 * A press places the cursor on its own, because on touch `pointermove` only
 * fires while a finger is down AND moving: tapping the chart without dragging
 * never moved it, so nothing appeared. Capturing the pointer also keeps a
 * scrub tracking once the finger wanders past the chart's edges.
 */
function onDown(event) {
    onMove(event);

    // Capture keeps a scrub tracking once the finger wanders past the chart's
    // edges. Placing the cursor comes first and is guarded separately, because
    // capturing a pointer id that is no longer active throws, and losing the
    // reading to that would be worse than losing the capture.
    try {
        event.currentTarget.setPointerCapture?.(event.pointerId);
    } catch {
        // Best effort: without capture a scrub simply stops at the edges.
    }
}

/**
 * Keep the readout after a touch lifts; only clear when a mouse leaves. A
 * lifting finger raises this too, which wiped the value in the same moment the
 * tap placed it. Matches SleepStages, which already reads touch this way.
 */
function onLeave(event) {
    if (event.pointerType !== 'touch') {
        props.cursor.clear();
    }
}
</script>

<template>
    <div
        ref="container"
        class="relative h-40 w-full touch-none select-none"
        @pointerdown="onDown"
        @pointermove="onMove"
        @pointerleave="onLeave"
    >
        <svg class="absolute inset-0 size-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient :id="`profile-fill-${unit}`" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="color" stop-opacity="0.22" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0" />
                </linearGradient>
            </defs>

            <path v-if="fill" :d="areaPath" :fill="`url(#profile-fill-${unit})`" />
            <path :d="linePath" fill="none" :stroke="color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
            <line v-if="hovered" :x1="hovered.left" y1="0" :x2="hovered.left" y2="100" stroke="var(--color-neutral-500)" stroke-width="1" stroke-opacity="0.4" vector-effect="non-scaling-stroke" />
        </svg>

        <template v-if="hovered">
            <div class="pointer-events-none absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-neutral-0" :style="{ left: `${hovered.left}%`, top: `${hovered.top}%`, background: color }" />
            <!-- Intentional dark chip in both themes (same pattern as Tooltip.vue / HeartRateChart). -->
            <div
                class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 whitespace-nowrap rounded-md bg-black px-2 py-1 text-xs font-medium text-white shadow-card tnum"
                :style="{ left: `${Math.min(90, Math.max(10, hovered.left))}%` }"
            >
                {{ Math.round(hovered.value) }} {{ unit }}<template v-if="hovered.time">, {{ hovered.time }}</template>
            </div>
        </template>
    </div>
</template>
