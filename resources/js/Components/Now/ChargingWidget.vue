<script setup>
import { computed } from 'vue';
import { BATTERY_COLOURS, batteryState } from '../../lib/battery.js';

const props = defineProps({
    device: { type: String, default: 'iPhone' },
    percent: { type: Number, default: 72 },
    // iOS's own estimate, which Shortcuts cannot read, so this is normally
    // absent and the line below it simply does not render.
    timeLeft: { type: String, default: null },
    charging: { type: Boolean, default: true },
    lowPower: { type: Boolean, default: false },
});

const clamped = computed(() => Math.round(Math.max(0, Math.min(100, props.percent))));

// The tile holds a percentage; the shared rule works in a 0-1 fraction.
const state = computed(() => batteryState({
    level: clamped.value / 100,
    charging: props.charging,
    lowPower: props.lowPower,
}));

/**
 * Deliberately not driven by `state`: the label describes what the phone is
 * doing, and a phone charging in Low Power Mode is still charging, whereas the
 * colour follows iOS in showing Low Power Mode. Keeping them separate is why the
 * two disagree in that one case.
 */
const statusText = computed(() => {
    if (props.charging) {
        return 'Charging…';
    }

    if (props.lowPower) {
        return 'Low Power Mode';
    }

    return state.value === 'low' ? 'Low battery' : 'On battery';
});

const subText = computed(() => (props.charging ? props.timeLeft : 'remaining'));
const hasSubText = computed(() => Boolean(subText.value));

// Colour priority mirrors iOS: Low Power Mode (orange) > low (red) > charging
// (green) > idle (green).
const VALUE_COLOURS = {
    ...BATTERY_COLOURS,
    idle: 'var(--color-neutral-900)',
};

const FILL_CLASSES = {
    'low-power': 'fill-power before:bg-black/22',
    low: 'bg-battery-low before:bg-white/50',
    charging: 'fill-charging animate-charging-breathe overflow-hidden before:bg-black/22 after:absolute after:inset-0 after:animate-charging-sheen after:bg-linear-100 after:from-transparent after:from-25% after:via-white/50 after:to-transparent after:to-75% motion-reduce:animate-none motion-reduce:after:hidden',
    idle: 'fill-idle shadow-md shadow-battery/30 before:bg-black/22',
};

const valueColor = computed(() => VALUE_COLOURS[state.value]);
const fillClass = computed(() => FILL_CLASSES[state.value]);
</script>

<template>
    <div class="@container relative aspect-square rounded-3xl bg-neutral-0 shadow-card">
        <div class="flex h-full flex-col justify-center p-3.5 @5xs:p-4.5 @4xs:p-5.5 @xs:p-7">
            <p class="flex items-center gap-1 text-2xs leading-none font-semibold whitespace-nowrap @5xs:gap-1.5 @5xs:text-xs @4xs:gap-2 @4xs:text-sm @xs:gap-2.5 @xs:text-lg">
                <svg v-if="charging" class="size-2.5 shrink-0 text-neutral-900 @5xs:size-3 @4xs:size-3.5 @xs:size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M13 2 4 13.5h6L9 22l10-12.5h-6z" />
                </svg>
                <span class="text-neutral-900">{{ device }}</span>
                <span class="text-neutral-500">{{ statusText }}</span>
            </p>

            <p class="mt-2 flex items-baseline gap-2 whitespace-nowrap tabular-nums @5xs:mt-2.5 @5xs:gap-2.5 @4xs:mt-3 @4xs:gap-3 @xs:mt-4.5 @xs:gap-4">
                <span class="text-base leading-none font-extrabold tracking-tight @5xs:text-xl @4xs:text-2xl @xs:text-4xl" :style="{ color: valueColor }">{{ clamped }}%</span>
                <span v-if="hasSubText" class="text-base leading-none font-extrabold tracking-tight text-neutral-900 @5xs:text-xl @4xs:text-2xl @xs:text-3xl">{{ subText }}</span>
            </p>

            <div class="mx-0.5 mt-3 mb-1.5 flex justify-between text-3xs leading-none font-semibold text-neutral-300 @5xs:mt-4 @4xs:mt-4.5 @4xs:mb-2 @4xs:text-2xs @xs:mx-1 @xs:mt-6 @xs:mb-2.5 @xs:text-sm">
                <span>0</span><span>50</span><span>100</span>
            </div>

            <div class="relative aspect-17/6">
                <div class="absolute inset-0 rounded-xl bg-neutral-50 @5xs:rounded-2xl @xs:rounded-3xl" />
                <div
                    class="absolute inset-y-0 left-0 rounded-md before:absolute before:top-1/2 before:right-2 before:h-27/50 before:w-px before:-translate-y-1/2 before:rounded-xs @5xs:rounded-lg @5xs:before:right-2.5 @4xs:rounded-2xl @4xs:before:right-3 @xs:rounded-3xl @xs:before:right-4 @xs:before:w-0.5"
                    :class="fillClass"
                    :style="{ width: `${clamped}%` }"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.fill-idle {
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-battery) 82%, var(--color-white)),
        var(--color-battery) 60%,
        color-mix(in srgb, var(--color-battery) 88%, var(--color-black))
    );
}

.fill-power {
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-battery-power) 80%, var(--color-white)),
        var(--color-battery-power) 60%,
        color-mix(in srgb, var(--color-battery-power) 92%, var(--color-black))
    );
    box-shadow:
        0 0 14px 2px color-mix(in srgb, var(--color-battery-power) 45%, transparent),
        inset 0 1px 2px color-mix(in srgb, var(--color-white) 35%, transparent);
}

.fill-charging {
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-battery-charging) 84%, var(--color-white)),
        var(--color-battery-charging) 60%,
        color-mix(in srgb, var(--color-battery-charging) 92%, var(--color-black))
    );
    box-shadow:
        0 0 18px 4px color-mix(in srgb, var(--color-battery-charging) 55%, transparent),
        0 5px 13px color-mix(in srgb, var(--color-battery-charging) 35%, transparent),
        inset 0 1px 2px color-mix(in srgb, var(--color-white) 35%, transparent);
}
</style>
