<script setup>
import { computed } from 'vue';

const props = defineProps({
    level: { type: Number, default: 0.69 },
    charging: { type: Boolean, default: false },
    lowPower: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const clamped = computed(() => Math.max(0, Math.min(1, props.level)));
const isLow = computed(() => clamped.value <= 0.2);

// Fill spans x2..23 (21 units) inside the body; keep a sliver visible near empty.
const fillWidth = computed(() => Math.max(2.5, clamped.value * 21).toFixed(1));

// iOS state colours: Low Power → yellow, low → red, charging → green, otherwise
// the foreground colour (inherits the surrounding text colour).
const fillColor = computed(() => {
    if (props.lowPower) {
        return '#FDC633';
    }

    if (isLow.value) {
        return '#FA3532';
    }

    if (props.charging) {
        return '#37C058';
    }

    return 'currentColor';
});
</script>

<template>
    <svg viewBox="0 0 27 13" :class="compact ? 'h-3 w-auto' : 'h-3.5 w-auto'" fill="none" aria-hidden="true">
        <rect x="0.5" y="0.5" width="24" height="12" rx="3.5" stroke="currentColor" stroke-opacity="0.4" />
        <rect x="2" y="2" :width="fillWidth" height="9" rx="2" :fill="fillColor" />
        <path
            d="M25.5 4.5C25.8978 4.5 26.2794 4.71071 26.5607 5.08579C26.842 5.46086 27 5.96957 27 6.5C27 7.03043 26.842 7.53914 26.5607 7.91421C26.2794 8.28929 25.8978 8.5 25.5 8.5L25.5 6.5V4.5Z"
            fill="currentColor"
            fill-opacity="0.4"
        />
        <path
            v-if="charging"
            d="M9 7.21604C9 7.33959 9.04111 7.44255 9.12332 7.52492C9.20554 7.60354 9.30742 7.64285 9.42895 7.64285H12.5979L10.9357 12.2817C10.8785 12.4427 10.8677 12.5812 10.9035 12.6972C10.9428 12.8133 11.0089 12.8976 11.1019 12.95C11.1948 13.0024 11.2985 13.0136 11.4129 12.9837C11.5308 12.9537 11.6399 12.8732 11.7399 12.7422L16.8499 6.17146C16.95 6.04042 17 5.90938 17 5.77835C17 5.65479 16.9589 5.55371 16.8767 5.47508C16.798 5.39271 16.6962 5.35153 16.571 5.35153H13.4075L15.0643 0.71272C15.1251 0.551729 15.1358 0.415073 15.0965 0.302753C15.0608 0.186689 14.9964 0.102449 14.9035 0.0500334C14.8105 -0.00238254 14.7051 -0.0136145 14.5871 0.0163374C14.4727 0.0462894 14.3655 0.124913 14.2654 0.252209L9.1555 6.82854C9.05183 6.95958 9 7.08874 9 7.21604Z"
            fill="var(--color-neutral-700)"
        />
    </svg>
</template>
