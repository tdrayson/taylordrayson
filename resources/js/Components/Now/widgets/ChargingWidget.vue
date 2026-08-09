<script setup>
import { computed } from 'vue';

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
const isLow = computed(() => clamped.value <= 20);

const statusText = computed(() => {
    if (props.charging) {
        return 'Charging…';
    }

    if (props.lowPower) {
        return 'Low Power';
    }

    return isLow.value ? 'Low battery' : 'On battery';
});

const subText = computed(() => (props.charging ? props.timeLeft : 'remaining'));
const hasSubText = computed(() => Boolean(subText.value));

// Colour priority mirrors iOS: Low Power (orange) > low (red) > charging
// (green) > idle (neutral foreground).
const valueColor = computed(() => {
    if (props.lowPower) {
        return '#FF9500';
    }

    if (isLow.value) {
        return '#FA3532';
    }

    return props.charging ? '#1BC95A' : 'var(--color-neutral-900)';
});

const fillModifier = computed(() => {
    if (props.lowPower) {
        return 'charging__fill--power';
    }

    if (isLow.value) {
        return 'charging__fill--low';
    }

    return props.charging ? 'charging__fill--charging' : 'charging__fill--idle';
});
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
    background: rgba(6, 80, 36, 0.22);
}

/* Idle (not charging) — a white battery, outlined so it reads on the track. */
/* On battery — dark, matching the percentage's own colour in this state. A
   white fill was invisible against the white card. */
.charging__fill--idle {
    background: linear-gradient(180deg, var(--color-neutral-700), var(--color-neutral-900) 60%);
    box-shadow: 0 1cqw 2cqw rgba(20, 22, 30, 0.18);
}

.charging__fill--idle::before {
    background: rgba(255, 255, 255, 0.45);
}

.charging__fill--low {
    background: #fa3532;
}

.charging__fill--low::before {
    background: rgba(255, 255, 255, 0.5);
}

/* Low Power Mode — orange, soft static glow. */
.charging__fill--power {
    background: linear-gradient(180deg, #ffb347, #ff9f0a 60%, #ff9500);
    box-shadow:
        0 0 7cqw 1cqw rgba(255, 149, 0, 0.45),
        inset 0 0.6cqw 1.2cqw rgba(255, 255, 255, 0.35);
}

.charging__fill--power::before {
    background: rgba(120, 60, 0, 0.22);
}

.charging__fill--charging {
    overflow: hidden;
    background: linear-gradient(180deg, #34e673, #16d957 60%, #10d052);
    box-shadow:
        0 0 9cqw 1.8cqw rgba(24, 221, 92, 0.55),
        0 2.4cqw 6.6cqw rgba(24, 221, 92, 0.35),
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
            0 0 7.8cqw 1.2cqw rgba(24, 221, 92, 0.48),
            0 2.4cqw 6.6cqw rgba(24, 221, 92, 0.3),
            inset 0 0.6cqw 1.2cqw rgba(255, 255, 255, 0.35);
    }

    50% {
        box-shadow:
            0 0 10.8cqw 2.4cqw rgba(24, 221, 92, 0.62),
            0 2.4cqw 7.8cqw rgba(24, 221, 92, 0.4),
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
