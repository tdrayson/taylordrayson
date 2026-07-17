<script setup>
import { computed } from 'vue';
import StatGrid from '../Stats/StatGrid.vue';
import LocationMap from '../Maps/LocationMap.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import { number } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

// Fuel-card saving: what the pump price would have cost minus the fuel-card
// price. Null unless a lower fuel-card cost is recorded.
const saving = computed(() => {
    const { cost, fuel_card_cost: fuelCard } = props.entry;

    return cost && fuelCard && cost > fuelCard ? cost - fuelCard : null;
});

// Street address shown under the map, blanks dropped (postcode/city may be missing).
const addressLine = computed(() =>
    [props.entry.address, props.entry.postcode, props.entry.city].filter(Boolean).join(', '),
);

// Fuel-purchase figures shown as display stats. Range and MPG are server-computed
// from the next fill (null on the latest fill), and StatGrid drops blank stats.
const stats = computed(() => [
    { label: 'Volume', value: number(props.entry.litres, 1), unit: 'L' },
    { label: 'Cost', value: props.entry.cost ? `£${number(props.entry.cost, 2)}` : null },
    { label: 'Per litre', value: props.entry.price_per_litre ? `£${number(props.entry.price_per_litre, 3)}` : null },
    { label: 'Saving', value: saving.value ? `£${number(saving.value, 2)}` : null },
    { label: 'Range', value: props.entry.miles_this_tank ? number(props.entry.miles_this_tank) : null, unit: 'mi' },
    { label: 'MPG', value: props.entry.mpg ? number(props.entry.mpg, 1) : null },
    { label: 'Odometer', value: number(props.entry.odometer), unit: 'mi' },
]);

// Coordinate object the controller attaches for located entries; null otherwise.
const location = computed(() => props.entry.location ?? null);
</script>

<template>
    <div class="space-y-8">
        <div v-if="entry.logo_url || entry.brand" class="flex items-center gap-3">
            <span v-if="entry.logo_url" class="inline-flex size-12 items-center justify-center overflow-hidden rounded-lg bg-white ring-1 ring-neutral-100">
                <img :src="entry.logo_url" :alt="entry.brand ? `${entry.brand} logo` : ''" class="size-full object-contain p-1.5">
            </span>
            <span v-if="entry.brand" class="font-display text-section">{{ entry.brand }} garage</span>
        </div>

        <div v-if="location" class="space-y-3">
            <LocationMap
                :lat="location.lat"
                :lng="location.lng"
                :label="entry.station_name || location.address"
                color="var(--color-fuel)"
            />
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <p v-if="entry.station_name || addressLine" class="text-meta text-neutral-600">
                    <span v-if="entry.station_name" class="font-medium text-neutral-900">{{ entry.station_name }}</span><span v-if="addressLine">{{ entry.station_name ? ', ' : '' }}{{ addressLine }}</span>
                </p>
                <ExternalLink :href="location.mapsUrl" label="View on Google Maps" />
            </div>
        </div>

        <StatGrid :stats="stats" />
    </div>
</template>
