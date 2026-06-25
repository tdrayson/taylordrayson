<script setup>
import { computed } from 'vue';

const props = defineProps({
    seconds: { type: Number, required: true },
});

const parts = computed(() => {
    const total = Math.max(0, Math.round(props.seconds));

    return {
        hours: Math.floor(total / 3600),
        minutes: Math.floor((total % 3600) / 60),
        seconds: total % 60,
    };
});

/** Compact units shown to the reader, e.g. 1h 33m (minutes zero-padded when hours show). */
const segments = computed(() => {
    const { hours, minutes, seconds } = parts.value;

    if (hours > 0) {
        return [
            { value: hours, unit: 'h', label: 'hour' },
            { value: String(minutes).padStart(2, '0'), unit: 'm', label: 'minute' },
        ];
    }

    if (minutes > 0) {
        return [{ value: minutes, unit: 'm', label: 'minute' }];
    }

    return [{ value: seconds, unit: 's', label: 'second' }];
});

/** ISO 8601 duration for the datetime attribute, e.g. PT1H33M12S. */
const isoDuration = computed(() => {
    const { hours, minutes, seconds } = parts.value;

    return `PT${hours ? `${hours}H` : ''}${minutes ? `${minutes}M` : ''}${seconds ? `${seconds}S` : ''}` || 'PT0S';
});

/** Full, precise duration spelled out for the tooltip. */
const fullLabel = computed(() => {
    const { hours, minutes, seconds } = parts.value;

    return [
        hours ? `${hours} ${hours === 1 ? 'hour' : 'hours'}` : null,
        minutes ? `${minutes} ${minutes === 1 ? 'minute' : 'minutes'}` : null,
        seconds ? `${seconds} ${seconds === 1 ? 'second' : 'seconds'}` : null,
    ].filter(Boolean).join(' ') || '0 seconds';
});
</script>

<template>
    <time :datetime="isoDuration" :title="fullLabel" class="tnum">
        <template v-for="(segment, index) in segments" :key="segment.unit">{{ index ? ' ' : '' }}{{ segment.value }}<abbr class="unit" :title="segment.label">{{ segment.unit }}</abbr></template>
    </time>
</template>

<style scoped>
.unit {
    margin-left: 0.05em;
    font-size: 0.7em;
    font-weight: 600;
    color: var(--color-neutral-500);
    text-decoration: none;
}
</style>
