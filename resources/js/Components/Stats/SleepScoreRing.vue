<script setup>
import { computed, onMounted, ref } from 'vue';

const mounted = ref(false);
onMounted(() => requestAnimationFrame(() => (mounted.value = true)));

const props = defineProps({
    score: { type: Number, required: true },
    durationScore: { type: Number, default: 0 },
    bedtimeScore: { type: Number, default: 0 },
    interruptionScore: { type: Number, default: 0 },
});

/** Apple's bands, highest first. */
const BANDS = [
    { min: 96, label: 'Excellent', color: 'var(--color-score-excellent)' },
    { min: 81, label: 'High', color: 'var(--color-score-high)' },
    { min: 61, label: 'OK', color: 'var(--color-score-ok)' },
    { min: 41, label: 'Low', color: 'var(--color-score-low)' },
    { min: 0, label: 'Very low', color: 'var(--color-score-poor)' },
];

const band = computed(() => BANDS.find((entry) => props.score >= entry.min) ?? BANDS[BANDS.length - 1]);

const STROKE = 9;
const RADIUS = 42;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const GAP = 0.025; // fraction of the circle between segments
const CAP = STROKE / 2 / CIRCUMFERENCE; // half the stroke, so round caps sit inside each slot

/**
 * One arc per component, its length proportional to the component's max weight
 * and its filled portion proportional to the points earned, so the ring shows
 * which part cost the score at a glance. Each arc is inset by half a stroke so
 * its rounded caps land inside its slot, leaving clean gaps between components.
 */
const segments = computed(() => {
    const parts = [
        { label: 'Duration', points: props.durationScore, max: 50, color: 'var(--color-score-duration)' },
        { label: 'Bedtime', points: props.bedtimeScore, max: 30, color: 'var(--color-score-bedtime)' },
        { label: 'Interruptions', points: props.interruptionScore, max: 20, color: 'var(--color-score-interruptions)' },
    ];

    const usable = 1 - GAP * parts.length;
    let cursor = 0;

    return parts.map((part) => {
        const slot = (part.max / 100) * usable;
        const filled = slot * Math.max(0, Math.min(1, part.points / part.max));
        const startDeg = (cursor + CAP) * 360;
        cursor += slot + GAP;

        const trackLen = Math.max(0, slot - 2 * CAP);
        const fillLen = Math.max(0, filled - 2 * CAP);

        return {
            ...part,
            startDeg,
            fillLen,
            trackDash: `${trackLen * CIRCUMFERENCE} ${CIRCUMFERENCE}`,
            fillDash: `${fillLen * CIRCUMFERENCE} ${CIRCUMFERENCE}`,
        };
    });
});
</script>

<template>
    <div class="flex items-center gap-8">
        <div class="relative size-36 shrink-0">
            <svg class="size-full -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                <g v-for="(segment, index) in segments" :key="segment.label" :transform="`rotate(${segment.startDeg} 50 50)`">
                    <circle cx="50" cy="50" :r="RADIUS" fill="none" :stroke="segment.color" stroke-width="9" stroke-linecap="round" stroke-opacity="0.18" :stroke-dasharray="segment.trackDash" />
                    <circle
                        v-if="segment.fillLen > 0"
                        class="ring-fill"
                        cx="50"
                        cy="50"
                        :r="RADIUS"
                        fill="none"
                        :stroke="segment.color"
                        stroke-width="9"
                        stroke-linecap="round"
                        :stroke-dasharray="segment.fillDash"
                        :stroke-dashoffset="mounted ? 0 : segment.fillLen * CIRCUMFERENCE"
                        :style="{ transitionDelay: `${index * 0.15}s` }"
                    />
                </g>
            </svg>
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="font-display text-stat font-extrabold leading-none tnum">{{ score }}</span>
            </div>
        </div>

        <div class="min-w-0">
            <div class="font-display text-name text-neutral-900">{{ band.label }}</div>

            <dl class="mt-3 space-y-1.5">
                <div v-for="segment in segments" :key="segment.label" class="flex items-center gap-2 text-meta text-neutral-700">
                    <span class="size-2.5 shrink-0 rounded-full" :style="{ background: segment.color }" />
                    <dt>{{ segment.label }}</dt>
                    <dd class="text-neutral-500 tnum">{{ segment.points }}/{{ segment.max }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
