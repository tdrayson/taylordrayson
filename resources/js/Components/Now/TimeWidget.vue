<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { clockParts, offsetMinutes } from '../../lib/time.js';

const props = defineProps({
    location: { type: String, default: 'Europe/London' },
    timezone: { type: String, default: 'Europe/London' },
});

const timeStr = ref('');
const meridiem = ref('');
const gmtStr = ref('');
const leftPct = ref(9);
let timer = null;

const pad = (n) => String(n).padStart(2, '0');

function computeGmt() {
    const minutes = offsetMinutes(props.timezone);
    const sign = minutes < 0 ? '-' : '+';
    const whole = Math.floor(Math.abs(minutes) / 60);
    const rest = Math.abs(minutes) % 60;

    gmtStr.value = `GMT${sign}${whole}${rest ? `.${rest}` : ''}`;
}

function tick() {
    const { hour: h, minute: m } = clockParts(props.timezone);
    let h12 = h % 12;
    if (h12 === 0) {
        h12 = 12;
    }
    timeStr.value = `${pad(h12)}:${pad(m)}`;
    meridiem.value = h < 12 ? 'AM' : 'PM';
    // Position across the day; the ruler spans 9%..91% of the card.
    const frac = (h + m / 60) / 24;
    leftPct.value = 9 + frac * 82;
}

onMounted(() => {
    computeGmt();
    tick();
    timer = window.setInterval(tick, 1000);
});

onBeforeUnmount(() => {
    if (timer) {
        window.clearInterval(timer);
    }
});
</script>

<template>
    <div class="clock rounded-3xl">
        <div class="clock__header">
            <h2 class="clock__location">{{ location }}</h2>
            <div class="clock__offset">{{ gmtStr }}</div>
        </div>

        <div class="clock__baseline" />
        <div class="clock__now-line" :style="{ left: `${leftPct}%` }" />
        <div class="clock__marker" :style="{ left: `${leftPct}%` }" />
        <div class="clock__ruler">
            <span class="clock__ruler-mark" style="left: 0%">12</span>
            <span class="clock__ruler-mark" style="left: 25%">6</span>
            <span class="clock__ruler-mark" style="left: 50%">12</span>
            <span class="clock__ruler-mark" style="left: 75%">6</span>
            <span class="clock__ruler-mark" style="left: 100%">12</span>
        </div>

        <div class="clock__time">
            <span class="clock__time-value">{{ timeStr }}</span>
            <span class="clock__time-meridiem">{{ meridiem }}</span>
        </div>
    </div>
</template>

<style scoped>
/* The card is the query container; text sizes in cqw (1cqw ≈ reference px ÷ 3.6)
   so the whole composition scales with the grid cell. Positions use % of the
   square card. */
.clock {
    container-type: inline-size;
    position: relative;
    overflow: hidden;
    aspect-ratio: 1 / 1;
    background: var(--color-neutral-0);
    color: var(--color-neutral-900);
    box-shadow: var(--shadow-card);
}

.clock__header {
    position: absolute;
    top: 8%;
    left: 9%;
    z-index: 3;
}

.clock__location {
    font-family: var(--font-sans);
    font-size: 10cqw;
    line-height: 1.12;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--color-neutral-900);
}

.clock__offset {
    margin-top: 1cqw;
    font-size: 5.5cqw;
    font-weight: 500;
    color: var(--color-neutral-400);
}

.clock__baseline {
    position: absolute;
    left: 9%;
    right: 9%;
    bottom: 13%;
    /* Floor the thickness so the line never rounds away when the card is
       narrow on small screens; it still scales up with the card above that. */
    height: max(1.5px, 0.5cqw);
    background: var(--color-neutral-50);
    z-index: 1;
}

.clock__now-line {
    position: absolute;
    top: 27%;
    bottom: 13%;
    /* Same minimum thickness as the baseline, so the current-time bar stays
       visible on narrow cards instead of collapsing to a sub-pixel width. */
    width: max(1.5px, 0.5cqw);
    background: var(--color-neutral-100);
    transform: translateX(-50%);
    z-index: 1;
}

.clock__marker {
    position: absolute;
    top: 40%;
    width: 4.2cqw;
    height: 4.2cqw;
    border-radius: 50%;
    background: var(--color-accent-500);
    transform: translate(-50%, -50%);
    box-shadow: 0 0.6cqw 2cqw rgba(56, 88, 233, 0.4);
    z-index: 2;
}

.clock__ruler {
    position: absolute;
    left: 9%;
    right: 9%;
    bottom: 6.5%;
    height: 5cqw;
    z-index: 1;
}

.clock__ruler-mark {
    position: absolute;
    transform: translateX(-50%);
    font-size: 3.9cqw;
    font-weight: 500;
    color: var(--color-neutral-400);
}

.clock__time {
    position: absolute;
    left: 9%;
    bottom: 16.5%;
    display: flex;
    align-items: baseline;
    gap: 2.5cqw;
    z-index: 2;
}

.clock__time-value {
    font-size: 15.5cqw;
    font-weight: 800;
    letter-spacing: -0.035em;
    color: var(--color-neutral-900);
    font-variant-numeric: tabular-nums;
}

.clock__time-meridiem {
    font-size: 11cqw;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--color-neutral-400);
}
</style>
