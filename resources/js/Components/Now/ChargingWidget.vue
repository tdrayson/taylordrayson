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

const FILL_MODIFIERS = {
    'low-power': 'charging__fill--power',
    low: 'charging__fill--low',
    charging: 'charging__fill--charging',
    idle: 'charging__fill--idle',
};

const valueColor = computed(() => VALUE_COLOURS[state.value]);
const fillModifier = computed(() => FILL_MODIFIERS[state.value]);
</script>

<template>
    <div class="charging relative aspect-square rounded-3xl bg-neutral-0 shadow-card">
        <div class="charging__inner flex h-full flex-col justify-center">
            <p class="charging__status flex items-center font-semibold">
                <svg v-if="charging" class="charging__bolt text-neutral-900" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M13 2 4 13.5h6L9 22l10-12.5h-6z" />
                </svg>
                <span class="text-neutral-900">{{ device }}</span>
                <span class="text-neutral-500">{{ statusText }}</span>
            </p>

            <p class="charging__value flex items-baseline tnum">
                <span class="charging__percent font-extrabold" :style="{ color: valueColor }">{{ clamped }}%</span>
                <span v-if="hasSubText" class="charging__time font-extrabold text-neutral-900">{{ subText }}</span>
            </p>

            <div class="charging__scale flex justify-between font-semibold text-neutral-300">
                <span>0</span><span>50</span><span>100</span>
            </div>

            <div class="charging__bar relative">
                <div class="charging__track absolute inset-0 bg-neutral-50" />
                <div class="charging__fill" :class="fillModifier" :style="{ width: `${clamped}%` }" />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* The card is the query container; everything inside sizes in cqw (1cqw ≈ the
   reference's px ÷ 3.32) so the whole composition scales with the grid cell. */
.charging {
    container-type: inline-size;
}

.charging__inner {
    padding: 9cqw;
}

.charging__status {
    gap: 3cqw;
    font-size: 5.8cqw;
    line-height: 1;
    letter-spacing: -0.01em;
    white-space: nowrap;
}

.charging__bolt {
    width: 6cqw;
    height: 6cqw;
    flex: none;
}

.charging__value {
    gap: 5cqw;
    margin-top: 5.4cqw;
    line-height: 1;
    white-space: nowrap;
}

.charging__percent {
    font-size: 10.5cqw;
    letter-spacing: -0.025em;
}

.charging__time {
    font-size: 10cqw;
    letter-spacing: -0.03em;
}

.charging__scale {
    margin: 7.8cqw 1.2cqw 3.3cqw;
    font-size: 4.2cqw;
    line-height: 1;
}

.charging__bar {
    height: 28.9cqw;
}

.charging__track {
    border-radius: 7.8cqw;
}

.charging__fill {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    border-radius: 7.2cqw;
}

/* Subtle terminal tick near the end of the fill, like a battery's nub. */
.charging__fill::before {
    content: '';
    position: absolute;
    right: 4.8cqw;
    top: 50%;
    transform: translateY(-50%);
    width: 0.6cqw;
    height: 54%;
    border-radius: 2px;
    background: rgba(0, 0, 0, 0.22);
}

.charging__fill--idle {
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-battery) 82%, white),
        var(--color-battery) 60%,
        color-mix(in srgb, var(--color-battery) 88%, black)
    );
    box-shadow: 0 1cqw 2cqw color-mix(in srgb, var(--color-battery) 30%, transparent);
}

.charging__fill--idle::before {
    background: rgba(0, 0, 0, 0.22);
}

.charging__fill--low {
    background: var(--color-battery-low);
}

.charging__fill--low::before {
    background: rgba(255, 255, 255, 0.5);
}

/* Low Power Mode — orange, soft static glow. */
.charging__fill--power {
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-battery-power) 80%, white),
        var(--color-battery-power) 60%,
        color-mix(in srgb, var(--color-battery-power) 92%, black)
    );
    box-shadow:
        0 0 7cqw 1cqw color-mix(in srgb, var(--color-battery-power) 45%, transparent),
        inset 0 0.6cqw 1.2cqw rgba(255, 255, 255, 0.35);
}

.charging__fill--power::before {
    background: rgba(0, 0, 0, 0.22);
}

.charging__fill--charging {
    overflow: hidden;
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-battery-charging) 84%, white),
        var(--color-battery-charging) 60%,
        color-mix(in srgb, var(--color-battery-charging) 92%, black)
    );
    box-shadow:
        0 0 9cqw 1.8cqw color-mix(in srgb, var(--color-battery-charging) 55%, transparent),
        0 2.4cqw 6.6cqw color-mix(in srgb, var(--color-battery-charging) 35%, transparent),
        inset 0 0.6cqw 1.2cqw rgba(255, 255, 255, 0.35);
    animation: charging-breathe 2.6s ease-in-out infinite;
}

/* Charging sheen — a soft highlight that sweeps left to right. */
.charging__fill--charging::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(100deg, transparent 25%, rgba(255, 255, 255, 0.5) 50%, transparent 75%);
    transform: translateX(-120%);
    animation: charging-sheen 2.4s ease-in-out infinite;
}

@keyframes charging-sheen {
    0% {
        transform: translateX(-120%);
    }

    60%,
    100% {
        transform: translateX(120%);
    }
}

@keyframes charging-breathe {
    0%,
    100% {
        box-shadow:
            0 0 7.8cqw 1.2cqw color-mix(in srgb, var(--color-battery-charging) 48%, transparent),
            0 2.4cqw 6.6cqw color-mix(in srgb, var(--color-battery-charging) 30%, transparent),
            inset 0 0.6cqw 1.2cqw rgba(255, 255, 255, 0.35);
    }

    50% {
        box-shadow:
            0 0 10.8cqw 2.4cqw color-mix(in srgb, var(--color-battery-charging) 62%, transparent),
            0 2.4cqw 7.8cqw color-mix(in srgb, var(--color-battery-charging) 40%, transparent),
            inset 0 0.6cqw 1.2cqw rgba(255, 255, 255, 0.4);
    }
}

@media (prefers-reduced-motion: reduce) {
    .charging__fill--charging,
    .charging__fill--charging::after {
        animation: none;
    }
}
</style>
