<script setup>
import { computed } from 'vue';
import { titleCase } from '../../lib/format.js';

const props = defineProps({
    platform: { type: String, required: true },
    url: { type: String, default: null },
});

/** Friendly names for known data sources; unknown keys fall back to title case. */
const PLATFORMS = {
    strava: 'Strava',
    trakt: 'Trakt',
    swarm: 'Swarm',
    foursquare: 'Foursquare',
    oura: 'Oura',
    apple_health: 'Apple Health',
    apple_watch: 'Apple Watch',
    clock: 'Apple Clock',
    setgraph: 'Setgraph',
};

const label = computed(() => PLATFORMS[props.platform] ?? titleCase(props.platform));
</script>

<template>
    <p class="border-t border-neutral-50 pt-4 text-caption text-neutral-500">
        Source:
        <a
            v-if="url"
            :href="url"
            target="_blank"
            rel="noopener noreferrer"
            class="font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500"
        >{{ label }}</a>
        <span v-else class="font-medium text-neutral-700">{{ label }}</span>
    </p>
</template>
