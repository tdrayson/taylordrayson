<script setup>
import { computed } from 'vue';
import StatGrid from '../Stats/StatGrid.vue';

const props = defineProps({
    // Shape from SeriesController::stats()/seasonStats(): episodesWatched and
    // totalHours are always present; seasons/progress/watchSpan may be null
    // (or absent entirely at season scope, where they don't apply).
    stats: { type: Object, required: true },
});

// Map the controller's stats object into StatGrid's { label, value, unit }
// tiles, omitting anything null/undefined so a sparser season-scoped stats
// object (no seasons/progress) never renders an empty tile.
const items = computed(() => {
    const list = [];

    if (props.stats.episodesWatched !== null && props.stats.episodesWatched !== undefined) {
        list.push({ label: 'Episodes watched', value: props.stats.episodesWatched });
    }

    if (props.stats.seasons !== null && props.stats.seasons !== undefined) {
        list.push({ label: 'Seasons', value: props.stats.seasons });
    }

    if (props.stats.progress !== null && props.stats.progress !== undefined) {
        list.push({ label: 'Progress', value: props.stats.progress, unit: '%' });
    }

    if (props.stats.watchSpan) {
        list.push({ label: 'Watch span', value: props.stats.watchSpan });
    }

    if (props.stats.totalHours !== null && props.stats.totalHours !== undefined) {
        // Server rounds to a whole number of hours but sends it as a float
        // (e.g. 7.0); round again client-side so it never renders "7.0".
        list.push({ label: 'Total time', value: Math.round(props.stats.totalHours), unit: 'h' });
    }

    return list;
});
</script>

<template>
    <StatGrid data-testid="series-stats" :stats="items" />
</template>
