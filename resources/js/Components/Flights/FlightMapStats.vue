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

/** Earth's equatorial circumference in metres, matching FuelStory's yardstick. */
const EARTH_CIRCUMFERENCE = 40_075_000;

/** Laps of the planet, the figure that makes a six-digit distance mean something. */
const laps = computed(() => {
    const value = props.stats.distance / EARTH_CIRCUMFERENCE;

    return value >= 0.1 ? `${value.toFixed(1)}x around the world` : null;
});

const airlinesLabel = computed(() => (props.stats.airlines > 0
    ? `across ${number(props.stats.airlines)} ${props.stats.airlines === 1 ? 'airline' : 'airlines'}`
    : null));

// The two figures the map is really about, given the room to read as headlines.
// distanceM is converted reactively by StatGrid against the visitor's unit toggle.
const headline = computed(() => [
    { label: 'Distance', distanceM: props.stats.distance, precision: 0, sub: laps.value },
    { label: 'Flights', value: number(props.stats.flights), sub: airlinesLabel.value },
]);

const secondary = computed(() => [
    { label: 'Airports', value: number(props.stats.airports) },
    { label: 'Time in air', seconds: props.stats.duration },
]);

// Standout route/aircraft for this bucket. DetailList drops any row whose
// value is null, so a missing highlight (e.g. no flights yet) just disappears.
const highlights = computed(() => [
    {
        label: 'Most flown',
        value: props.stats.topRoute
            ? `${props.stats.topRoute}, ${props.stats.topRouteCount} ${props.stats.topRouteCount === 1 ? 'flight' : 'flights'}`
            : null,
    },
    {
        label: 'Longest',
        value: props.stats.longestRoute
            ? `${props.stats.longestRoute}, ${distance(props.stats.longestDistance)}`
            : null,
    },
    {
        label: 'Aircraft',
        value: props.stats.topAircraft
            ? `${props.stats.topAircraft}, ${props.stats.topAircraftCount} ${props.stats.topAircraftCount === 1 ? 'flight' : 'flights'}`
            : null,
    },
]);
</script>

<template>
    <div class="space-y-4">
        <!-- Two tiers of figure rather than five of equal weight: the panel is
             narrow, and everything reading the same size reads as a table. -->
        <StatGrid :stats="headline" class="grid grid-cols-2 gap-x-4 gap-y-5" />
        <StatGrid :stats="secondary" size="sm" class="grid grid-cols-2 gap-x-4 gap-y-4" />

        <!-- Rules-free rows on the panel's own tint: the band already groups
             them, so a card or a stack of hairlines would both be noise. The
             gap does the separating instead, so it has to be bigger than the
             one between the two stat tiers. -->
        <DetailList :rows="highlights" variant="plain" class="pt-3" />
    </div>
</template>
