<script setup>
import { computed } from 'vue';
import StatGrid from '../Stats/StatGrid.vue';
import DetailList from '../Ui/DetailList.vue';
import { useFormat } from '../../composables/useFormat';
import { number } from '../../lib/format.js';

/**
 * Stats card for the flight globe map's current bucket (all-time or one year).
 * @param {object} stats A FlightMapStats payload.
 */
const props = defineProps({
    stats: { type: Object, required: true },
});

const { distance } = useFormat();

// distanceM and seconds rows are converted reactively by StatGrid against the
// visitor's unit toggle; plain value rows pass straight through.
const rows = computed(() => [
    { label: 'Flights', value: number(props.stats.flights) },
    { label: 'Distance', distanceM: props.stats.distance, precision: 0 },
    { label: 'Time in air', seconds: props.stats.duration },
    { label: 'Airports', value: number(props.stats.airports) },
    { label: 'Airlines', value: number(props.stats.airlines) },
]);

// Standout route/aircraft for this bucket. DetailList drops any row whose
// value is null, so a missing highlight (e.g. no flights yet) just disappears.
const highlights = computed(() => [
    {
        label: 'Top route',
        value: props.stats.topRoute
            ? `${props.stats.topRoute}, ${props.stats.topRouteCount} ${props.stats.topRouteCount === 1 ? 'flight' : 'flights'}`
            : null,
    },
    {
        label: 'Longest route',
        value: props.stats.longestRoute
            ? `${props.stats.longestRoute}, ${distance(props.stats.longestDistance)}`
            : null,
    },
    {
        label: 'Top aircraft',
        value: props.stats.topAircraft
            ? `${props.stats.topAircraft}, ${props.stats.topAircraftCount} ${props.stats.topAircraftCount === 1 ? 'flight' : 'flights'}`
            : null,
    },
]);
</script>

<template>
    <div class="space-y-4">
        <StatGrid :stats="rows" />
        <DetailList :rows="highlights" />
    </div>
</template>
