<script setup>
import { computed } from 'vue';
import { weatherFor } from '../../lib/weather.js';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    temp: { type: String, default: '25°C' },
    condition: { type: String, default: 'partly-cloudy' },
    compact: { type: Boolean, default: false },
});

// The same table the Now tile reads. This used to be a separate list matched by
// substring, which quietly disagreed with the tile: "mostly-sunny" hit the
// catch-all "sun" rule here and drew a bare sun, while the tile drew sun behind
// cloud. One source, one icon.
const icon = computed(() => weatherFor(props.condition).icon);
</script>

<template>
    <span class="weather-status inline-flex items-center gap-1.5">
        <Icon :icon="icon" :class="['text-neutral-500', compact ? 'size-3.5' : 'size-4']" />
        <span class="tnum">{{ temp }}</span>
    </span>
</template>
