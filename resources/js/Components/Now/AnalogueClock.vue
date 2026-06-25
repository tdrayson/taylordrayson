<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const now = ref(new Date());
let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});

const seconds = computed(() => now.value.getSeconds());
const minutes = computed(() => now.value.getMinutes());
const hours = computed(() => now.value.getHours() % 12);

const secondAngle = computed(() => seconds.value * 6);
const minuteAngle = computed(() => minutes.value * 6 + seconds.value * 0.1);
const hourAngle = computed(() => hours.value * 30 + minutes.value * 0.5);

const ticks = Array.from({ length: 12 }, (_, index) => {
    const angle = (index * 30 * Math.PI) / 180;
    const major = index % 3 === 0;
    const inner = major ? 37 : 41;

    return {
        x1: 50 + Math.sin(angle) * inner,
        y1: 50 - Math.cos(angle) * inner,
        x2: 50 + Math.sin(angle) * 45,
        y2: 50 - Math.cos(angle) * 45,
        major,
    };
});
</script>

<template>
    <svg viewBox="0 0 100 100" class="clock" aria-hidden="true">
        <circle class="face" cx="50" cy="50" r="47" />
        <line
            v-for="(tick, index) in ticks"
            :key="index"
            class="tick"
            :class="{ major: tick.major }"
            :x1="tick.x1"
            :y1="tick.y1"
            :x2="tick.x2"
            :y2="tick.y2"
        />
        <line class="hand hour" x1="50" y1="56" x2="50" y2="29" :style="{ transform: `rotate(${hourAngle}deg)` }" />
        <line class="hand minute" x1="50" y1="57" x2="50" y2="18" :style="{ transform: `rotate(${minuteAngle}deg)` }" />
        <line class="hand second" x1="50" y1="60" x2="50" y2="14" :style="{ transform: `rotate(${secondAngle}deg)` }" />
        <circle class="pin" cx="50" cy="50" r="2.6" />
    </svg>
</template>

<style scoped>
.clock {
    width: 100%;
    height: 100%;
    overflow: visible;
}

.face {
    fill: var(--color-neutral-0);
    stroke: currentColor;
    stroke-width: 2;
    stroke-opacity: 0.18;
}

.tick {
    stroke: currentColor;
    stroke-width: 1.4;
    stroke-linecap: round;
    stroke-opacity: 0.4;
}

.tick.major {
    stroke-width: 2.4;
    stroke-opacity: 0.75;
}

.hand {
    stroke: currentColor;
    stroke-linecap: round;
    transform-box: view-box;
    transform-origin: 50px 50px;
}

.hand.hour {
    stroke-width: 4.5;
}

.hand.minute {
    stroke-width: 3.2;
}

.hand.second {
    stroke: var(--color-accent-500);
    stroke-width: 1.8;
}

.pin {
    fill: var(--color-accent-500);
}
</style>
