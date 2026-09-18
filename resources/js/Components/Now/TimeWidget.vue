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

const RULER_MARKS = [
    { label: '12', position: 'left-0' },
    { label: '6', position: 'left-1/4' },
    { label: '12', position: 'left-1/2' },
    { label: '6', position: 'left-3/4' },
    { label: '12', position: 'left-full' },
];

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
    <div class="@container relative aspect-square overflow-hidden rounded-3xl bg-neutral-0 text-neutral-900 shadow-card">
        <div class="absolute top-2/25 left-9/100 z-3">
            <h2 class="text-base leading-tight font-extrabold tracking-tight @5xs:text-xl @4xs:text-2xl @xs:text-3xl">{{ location }}</h2>
            <div class="mt-0.5 text-3xs font-medium text-neutral-400 @5xs:text-2xs @4xs:text-sm @xs:mt-1 @xs:text-lg">{{ gmtStr }}</div>
        </div>

        <div class="absolute right-9/100 bottom-13/100 left-9/100 z-1 h-0.5 bg-neutral-50" />
        <div class="absolute top-27/100 bottom-13/100 z-1 w-0.5 -translate-x-1/2 bg-neutral-100" :style="{ left: `${leftPct}%` }" />
        <div
            class="absolute top-2/5 z-2 size-1.5 -translate-1/2 rounded-full bg-accent-500 shadow-sm shadow-accent-500/40 @5xs:size-2 @4xs:size-2.5 @xs:size-3.5 @xs:shadow-md"
            :style="{ left: `${leftPct}%` }"
        />
        <div class="absolute right-9/100 bottom-13/200 left-9/100 z-1 h-1/20">
            <span
                v-for="(mark, index) in RULER_MARKS"
                :key="index"
                class="absolute -translate-x-1/2 text-3xs font-medium text-neutral-400 @4xs:text-2xs @xs:text-xs"
                :class="mark.position"
            >
                {{ mark.label }}
            </span>
        </div>

        <div class="absolute bottom-33/200 left-9/100 z-2 flex items-baseline gap-1 @4xs:gap-1.5 @xs:gap-2">
            <span class="text-2xl leading-normal font-extrabold tracking-tight tabular-nums @5xs:text-3xl @4xs:text-4xl @xs:text-5xl">{{ timeStr }}</span>
            <span class="text-lg leading-normal font-extrabold tracking-tight text-neutral-400 @5xs:text-xl @4xs:text-2xl @xs:text-4xl">{{ meridiem }}</span>
        </div>
    </div>
</template>
