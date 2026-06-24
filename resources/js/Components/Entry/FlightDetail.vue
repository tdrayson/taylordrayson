<script setup>
import { computed } from 'vue';
import FlightRoute from '../Maps/FlightRoute.vue';
import FlightMap from '../Maps/FlightMap.vue';
import StatRow from '../Stats/StatRow.vue';
import { number, titleCase, time, flightDurationLabel } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const meta = computed(() => props.entry.meta || {});
const airline = computed(() => props.entry.airline || null);
const origin = computed(() => props.entry.origin || {});
const destination = computed(() => props.entry.destination || {});

const departAt = computed(() => meta.value.departed_actual || meta.value.departed_scheduled || props.entry.occurred_at);
const arriveAt = computed(() => meta.value.arrived_actual || meta.value.arrived_scheduled || null);
const durationLabel = computed(() => flightDurationLabel(props.entry.distance_miles));
const distanceLabel = computed(() => (props.entry.distance_miles ? `${number(props.entry.distance_miles)} mi` : null));

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
                :alt="airline.name"
                class="h-8 w-auto object-contain"
            >
            <span v-else-if="airline" class="font-display text-section">{{ airline.name }}</span>
            <div v-if="flightNumber" class="ml-auto text-right">
                <div class="text-label uppercase text-ink-3">Flight</div>
                <div class="text-section text-ink-2 tnum">{{ flightNumber }}</div>
            </div>
        </div>

        <div class="rounded-lg border border-line-2 px-6 py-6">
            <FlightRoute
                :origin="{ iata: entry.origin_iata, city: origin.place, name: origin.name }"
                :destination="{ iata: entry.destination_iata, city: destination.place, name: destination.name }"
                :depart-time="time(departAt)"
                :arrive-time="time(arriveAt)"
                :duration="durationLabel"
                :note="distanceLabel"
            />
        </div>

        <FlightMap
            v-if="hasCoordinates"
            :origin="{ lat: origin.latitude, lng: origin.longitude, iata: entry.origin_iata }"
            :destination="{ lat: destination.latitude, lng: destination.longitude, iata: entry.destination_iata }"
            color="var(--color-flight)"
        />

        <StatRow :stats="stats" />
    </div>
</template>
