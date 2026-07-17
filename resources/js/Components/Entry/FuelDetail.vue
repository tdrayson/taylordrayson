<script setup>
import { computed } from 'vue';
import StatGrid from '../Stats/StatGrid.vue';
import DetailList from '../Ui/DetailList.vue';
import LocationMap from '../Maps/LocationMap.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import { number } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

// Fuel-purchase figures shown as display stats (blanks dropped by StatGrid).
const stats = computed(() => [
    { label: 'Volume', value: number(props.entry.litres, 1), unit: 'L' },
    { label: 'Cost', value: props.entry.cost ? `£${number(props.entry.cost, 2)}` : null },
    { label: 'Per litre', value: props.entry.price_per_litre ? `£${number(props.entry.price_per_litre, 3)}` : null },
    { label: 'Odometer', value: number(props.entry.odometer), unit: 'mi' },
]);

// Garage identity + address rows; StatGrid/DetailList drop the blank ones.
const rows = computed(() => [
    { label: 'Brand', value: props.entry.brand },
    { label: 'Address', value: props.entry.address },
    { label: 'Postcode', value: props.entry.postcode },
    { label: 'City', value: props.entry.city },
    { label: 'Fuel card cost', value: props.entry.fuel_card_cost ? `£${number(props.entry.fuel_card_cost, 2)}` : null },
]);

// Coordinate object the controller attaches for located entries; null otherwise.
const location = computed(() => props.entry.location ?? null);
</script>

<template>
    <div class="space-y-8">
        <div v-if="entry.logo_url" class="flex items-center gap-3">
            <span class="inline-flex size-12 items-center justify-center overflow-hidden rounded-lg bg-white ring-1 ring-neutral-100">
                <img :src="entry.logo_url" :alt="entry.brand ? `${entry.brand} logo` : ''" class="size-full object-contain p-1.5">
            </span>
        </div>
        <div v-if="location" class="space-y-3">
            <LocationMap
                :lat="location.lat"
                :lng="location.lng"
                :label="entry.station_name || location.address"
                color="var(--color-fuel)"
            />
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <p v-if="entry.city" class="text-meta text-neutral-600">
                    <span>{{ entry.city }}</span>
                </p>
                <ExternalLink :href="location.mapsUrl" label="View on Google Maps" />
            </div>
        </div>

        <StatGrid :stats="stats" />
        <DetailList :rows="rows" />
    </div>
</template>
