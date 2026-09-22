<script setup>
import { computed } from 'vue';
import FlightRoute from '../Maps/FlightRoute.vue';
import FlightMap from '../Maps/FlightMap.vue';
import StatGrid from '../Stats/StatGrid.vue';
import Eyebrow from '../Ui/Eyebrow.vue';
import Heading from '../Ui/Heading.vue';
import { titleCase, time, duration as clockDuration, flightDurationLabel } from '../../lib/format.js';
import { metresToMiles } from '../../lib/distance.js';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    entry: { type: Object, required: true },
});

// Unit-aware distance formatter; the visible label re-runs when the visitor
// toggles distance units, while distanceMiles (below) stays fixed in miles
// for the duration estimate.
const { distance, exactDistance, duration, exactMeasure } = useFormat();

const meta = computed(() => props.entry.meta || {});
const airline = computed(() => props.entry.airline || null);
const origin = computed(() => props.entry.origin || {});
const destination = computed(() => props.entry.destination || {});

const departAt = computed(() => props.entry.departed_local);
const arriveAt = computed(() => props.entry.arrived_local);
// Raw distance is stored in metres; convert once here for the duration estimate and the label.
const distanceMiles = computed(() => metresToMiles(props.entry.distance));
const durationLabel = computed(() => (props.entry.duration ? duration(props.entry.duration) : flightDurationLabel(distanceMiles.value)));
const distanceLabel = computed(() => distance(props.entry.distance));

const hasCoordinates = computed(() => origin.value.latitude != null && destination.value.latitude != null);

const flightNumber = computed(() => {
    const code = airline.value?.iata_code || props.entry.airline_icao;

    return code ? `${code} ${props.entry.flight_number}` : props.entry.flight_number;
});

const stats = computed(() => [
    { label: 'Aircraft', value: meta.value.aircraft },
    { label: 'Cabin', value: titleCase(props.entry.cabin_class) },
    {
        label: 'Seat',
        value: meta.value.seat || titleCase(meta.value.seat_type),
        unit: meta.value.seat ? titleCase(meta.value.seat_type) : '',
    },
]);
</script>

<template>
    <div class="space-y-8">
        <div v-if="airline || flightNumber" class="flex items-end gap-3">
            <img
                v-if="airline?.logo_url"
                :src="airline.logo_url"
                :alt="airline.name || 'Airline logo'"
                :title="airline.name"
                class="h-8 w-auto object-contain"
            >
            <Heading v-else-if="airline" as="span" size="section">{{ airline.name }}</Heading>
            <div v-if="flightNumber" class="ml-auto text-right">
                <Eyebrow class="text-neutral-500">Flight</Eyebrow>
                <div class="text-lg font-bold leading-tight tracking-tight text-neutral-700 tabular-nums">{{ flightNumber }}</div>
            </div>
        </div>

        <div class="rounded-lg border border-neutral-50 px-6 py-6">
            <FlightRoute
                :origin="{ iata: entry.origin_iata, city: origin.place, name: origin.name }"
                :destination="{ iata: entry.destination_iata, city: destination.place, name: destination.name }"
                :depart-time="time(departAt)"
                :arrive-time="time(arriveAt)"
                :duration="durationLabel"
                :duration-title="exactMeasure('duration', entry.duration, clockDuration(entry.duration))"
                :note="distanceLabel"
                :note-title="exactDistance(entry.distance)"
            />
        </div>

        <FlightMap
            v-if="hasCoordinates"
            :origin="{ lat: origin.latitude, lng: origin.longitude, iata: entry.origin_iata }"
            :destination="{ lat: destination.latitude, lng: destination.longitude, iata: entry.destination_iata }"
            color="var(--color-flight)"
        />

        <StatGrid :stats="stats" />
    </div>
</template>
