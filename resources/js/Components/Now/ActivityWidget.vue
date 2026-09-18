<script setup>
import { reactive, computed, onMounted, useId } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'light' },
    // When true, the card fills its grid cell height (to match square tiles)
    // instead of imposing its own 2:1 ratio.
    fill: { type: Boolean, default: false },
    move: { type: Number, default: 137 },
    moveGoal: { type: Number, default: 190 },
    exercise: { type: Number, default: 24 },
    exerciseGoal: { type: Number, default: 30 },
    stand: { type: Number, default: 9 },
    standGoal: { type: Number, default: 12 },
});

const uid = useId();
const gradId = (key) => `${uid}-${key}`;

const TAU = 2 * Math.PI;

const rings = computed(() =>
    [
        { key: 'move', r: 42, label: 'Move', unit: 'CAL', value: props.move, goal: props.moveGoal, color: 'var(--color-ring-move)', light: 'var(--color-ring-move-light)', text: 'text-ring-move' },
        { key: 'exercise', r: 30, label: 'Exercise', unit: 'MIN', value: props.exercise, goal: props.exerciseGoal, color: 'var(--color-ring-exercise)', light: 'var(--color-ring-exercise-light)', text: 'text-ring-exercise' },
        { key: 'stand', r: 18, label: 'Stand', unit: 'HRS', value: props.stand, goal: props.standGoal, color: 'var(--color-ring-stand)', light: 'var(--color-ring-stand-light)', text: 'text-ring-stand' },
    ].map((ring) => {
        const circ = TAU * ring.r;
        const fraction = Math.max(0, Math.min(1, ring.goal ? ring.value / ring.goal : 0));

        return { ...ring, circ, offset: circ * (1 - fraction) };
    }),
);

const labelClass = (ring) => (ring.key === 'exercise' && props.variant === 'light' ? 'text-ring-exercise-dark' : ring.text);

// Rings start empty and counters at zero; both animate to target on mount.
const offsets = reactive({});
const counts = reactive({});
rings.value.forEach((ring) => {
    offsets[ring.key] = ring.circ;
    counts[ring.key] = 0;
});

onMounted(() => {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduce) {
        rings.value.forEach((ring) => {
            offsets[ring.key] = ring.offset;
            counts[ring.key] = ring.value;
        });
        return;
    }

    // Draw the rings on (paint the empty state first, then transition).
    requestAnimationFrame(() =>
        requestAnimationFrame(() => {
            rings.value.forEach((ring) => {
                offsets[ring.key] = ring.offset;
            });
        }),
    );

    // Count the metrics up with an ease-out.
    const start = performance.now();
    const duration = 1100;
    const tick = (now) => {
        const p = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        rings.value.forEach((ring) => {
            counts[ring.key] = Math.round(ring.value * eased);
        });
        if (p < 1) {
            requestAnimationFrame(tick);
        }
    };
    requestAnimationFrame(tick);
});
</script>

<template>
    <div
        class="@container rounded-3xl shadow-card"
        :class="[variant === 'dark' ? 'bg-linear-165 from-ring-face to-ring-face-deep text-white' : 'bg-neutral-0 text-neutral-900', { 'aspect-2/1': !fill }]"
    >
        <div class="flex h-full items-center gap-2.75 px-4 py-2.5 @sm:gap-3.25 @sm:px-5 @sm:py-3 @md:gap-4 @md:px-6 @md:py-3.75 @xl:gap-5.25 @xl:px-8 @xl:py-5">
            <div class="aspect-square w-7/15 flex-none drop-shadow-rings">
                <svg class="block size-full" viewBox="0 0 100 100">
                    <defs>
                        <linearGradient v-for="ring in rings" :id="gradId(ring.key)" :key="ring.key" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" :style="{ stopColor: ring.light }" />
                            <stop offset="1" :style="{ stopColor: ring.color }" />
                        </linearGradient>
                    </defs>

                    <circle
                        v-for="ring in rings"
                        :key="`track-${ring.key}`"
                        class="fill-none stroke-9"
                        :class="variant === 'dark' ? 'opacity-20' : 'opacity-16'"
                        cx="50"
                        cy="50"
                        :r="ring.r"
                        :style="{ stroke: ring.color }"
                    />
                    <circle
                        v-for="(ring, i) in rings"
                        :key="`fill-${ring.key}`"
                        class="ring-draw fill-none stroke-9"
                        cx="50"
                        cy="50"
                        :r="ring.r"
                        stroke-linecap="round"
                        transform="rotate(-90 50 50)"
                        :stroke="`url(#${gradId(ring.key)})`"
                        :stroke-dasharray="ring.circ"
                        :style="{ strokeDashoffset: offsets[ring.key], transitionDelay: `${0.05 + i * 0.12}s` }"
                    />
                </svg>
            </div>

            <div class="flex flex-1 flex-col justify-center gap-2.5 pl-1.25 @sm:gap-3 @sm:pl-1.5 @md:gap-3.75 @md:pl-1.75 @xl:gap-5 @xl:pl-2.25">
                <div v-for="ring in rings" :key="ring.key">
                    <div class="text-2xs font-extrabold @sm:text-xs @md:text-sm @xl:text-xl" :class="labelClass(ring)">{{ ring.label }}</div>
                    <div class="mt-0.5 text-xl leading-none font-extrabold tracking-tight tabular-nums @sm:text-2xl @md:text-3xl @xl:mt-0.75 @xl:text-4xl">
                        {{ counts[ring.key] }}<small
                            class="text-2xs leading-none font-semibold tracking-normal @sm:text-xs @md:text-sm @xl:text-xl"
                            :class="variant === 'dark' ? 'text-white/50' : 'text-neutral-400'"
                        >/{{ ring.goal }} {{ ring.unit }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ring-draw {
    transition: stroke-dashoffset 1.2s cubic-bezier(0.32, 1, 0.38, 1);
}

@media (prefers-reduced-motion: reduce) {
    .ring-draw {
        transition: none;
    }
}
</style>
