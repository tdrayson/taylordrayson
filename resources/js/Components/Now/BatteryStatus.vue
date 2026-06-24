<script setup>
import { computed } from 'vue';

const props = defineProps({
    level: { type: Number, default: 0.69 },
    charging: { type: Boolean, default: false },
    mono: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const clamped = computed(() => Math.max(0, Math.min(1, props.level)));
const fillWidth = computed(() => (clamped.value * 13).toFixed(1));
const fillColor = computed(() => {
    if (props.mono) {
        return 'currentColor';
    }

    return clamped.value <= 0.2 ? '#ff3b30' : '#34c759';
});
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        :class="compact ? 'size-6' : 'size-7'"
        aria-hidden="true"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <path
            d="M14 6H8C5.17157 6 3.75736 6 2.87868 6.87868C2 7.75736 2 9.17157 2 12C2 14.8284 2 16.2426 2.87868 17.1213C3.75736 18 5.17157 18 8 18H14C16.8284 18 18.2426 18 19.1213 17.1213C20 16.2426 20 14.8284 20 12C20 9.17157 20 7.75736 19.1213 6.87868C18.2426 6 16.8284 6 14 6Z"
        />
        <path d="M20 10H21C21.5523 10 22 10.4477 22 11V13C22 13.5523 21.5523 14 21 14H20M21 10.5V13.5" />
        <rect x="4" y="8.5" :width="fillWidth" height="7" rx="1.5" :fill="fillColor" stroke="none" />
        <path
            v-if="charging"
            d="M6.19351 11.3965L12.192 3.31186C12.6611 2.67957 13.5405 3.07311 13.5405 3.91536V10.1729C13.5405 10.6775 13.8853 11.0865 14.3107 11.0865H17.2283C17.891 11.0865 18.2443 12.0134 17.8065 12.6035L11.808 20.6881C11.3389 21.3204 10.4595 20.9269 10.4595 20.0846V13.8271C10.4595 13.3225 10.1147 12.9135 9.68931 12.9135H6.77173C6.10895 12.9135 5.75566 11.9866 6.19351 11.3965Z"
            transform="translate(5.9 6.9) scale(0.42)"
            fill="var(--color-canvas)"
            stroke="currentColor"
            stroke-width="1.3"
            vector-effect="non-scaling-stroke"
        />
    </svg>
</template>
