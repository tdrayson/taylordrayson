<script setup>
import { computed } from 'vue';
import Tooltip from '../Ui/Tooltip.vue';

/**
 * How much of a capped field's budget is spent, as a ring that fills.
 *
 * A number counting down reads as a rule; a ring that only takes on colour near
 * the end reads as a nudge, which is what the note limit is meant to be. The
 * exact figure only appears once it is worth knowing, at which point it is a
 * question of whether the next sentence fits rather than of pacing.
 */
const props = defineProps({
    used: { type: Number, required: true },
    max: { type: Number, required: true },
});

// Where the ring stops being quiet. Amber is a heads-up that the end is in
// sight; red is kept for actually being over, now that the exact figure is on
// screen by then. Colouring a legible 700 / 750 red says something is wrong
// when nothing is.
const WARN_AT = 0.65;

/** How close to the cap the running count is worth showing. */
const COUNT_WITHIN = 50;

const RADIUS = 8;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const fraction = computed(() => (props.max > 0 ? props.used / props.max : 0));

const over = computed(() => props.used > props.max);

// Clamped so a blown limit shows a full ring rather than wrapping past it.
const dashOffset = computed(() => CIRCUMFERENCE * (1 - Math.min(fraction.value, 1)));

const counting = computed(() => props.used >= props.max - COUNT_WITHIN);

const tone = computed(() => {
    if (over.value) {
        return 'text-red-500';
    }

    return fraction.value >= WARN_AT ? 'text-amber-500' : 'text-neutral-300';
});

const label = computed(() => `${props.used.toLocaleString()} of ${props.max.toLocaleString()} characters`);
</script>

<template>
    <div class="flex items-center gap-1.5" :class="tone">
        <!-- Tabular figures, or the count jogs left and right as it climbs. -->
        <span
            v-if="counting"
            class="text-label tabular-nums"
            :class="over ? 'font-semibold' : ''"
            aria-hidden="true"
        >{{ used.toLocaleString() }} / {{ max.toLocaleString() }}</span>

        <Tooltip :label="label" placement="top">
            <span class="inline-flex" role="img" :aria-label="label">
                <svg viewBox="0 0 20 20" class="size-5 -rotate-90" aria-hidden="true">
                    <circle cx="10" cy="10" :r="RADIUS" fill="none" stroke="currentColor" stroke-width="2" class="text-neutral-100" />
                    <circle
                        cx="10"
                        cy="10"
                        :r="RADIUS"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        :stroke-dasharray="CIRCUMFERENCE"
                        :stroke-dashoffset="dashOffset"
                        class="transition-[stroke-dashoffset] duration-200"
                    />
                </svg>
            </span>
        </Tooltip>
    </div>
</template>
