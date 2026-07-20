<script setup>
import { computed } from 'vue';
import StatGrid from '../Stats/StatGrid.vue';
import LocationMap from '../Maps/LocationMap.vue';
import DetailList from '../Ui/DetailList.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import Icon from '../Ui/Icon.vue';
import { number, money } from '../../lib/format.js';
import { milesToMetres } from '../../lib/distance.js';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    entry: { type: Object, required: true },
});

const { distanceFromMiles } = useFormat();

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

// Purchase figures in the display stats. Economy / card saving sit in the
// detail list below — StatGrid and DetailList both drop blank rows.
// Odometer is stored in miles; convert to metres so StatGrid can honour the
// visitor's mi/km distance setting.
const stats = computed(() => [
    { label: 'Volume', value: number(props.entry.litres, 1), unit: 'L' },
    { label: 'Cost', value: money(props.entry.cost) },
    { label: 'Per litre', value: props.entry.price_per_litre ? `£${number(props.entry.price_per_litre, 3)}` : null },
    {
        label: 'Odometer',
        distanceM: props.entry.odometer != null ? milesToMetres(props.entry.odometer) : null,
    },
]);

const details = computed(() => [
    { label: 'Fuel card saving', value: money(saving.value) },
    {
        label: 'Range',
        value: props.entry.miles_this_tank != null
            ? distanceFromMiles(props.entry.miles_this_tank)
            : null,
    },
    {
        label: 'Fuel economy',
        value: props.entry.mpg != null ? `${number(props.entry.mpg, 1)} mpg` : null,
    },
]);

const hasDetails = computed(() =>
    details.value.some((row) => row.value !== null && row.value !== undefined && row.value !== ''),
);

// Coordinate object the controller attaches for located entries; null otherwise.
const location = computed(() => props.entry.location ?? null);
</script>

<template>
    <div class="space-y-8">
        <div v-if="entry.logo_url || entry.brand || entry.vehicle" class="flex flex-wrap items-center gap-3">
            <span v-if="entry.logo_url" class="inline-flex size-12 items-center justify-center overflow-hidden rounded-lg bg-white ring-1 ring-neutral-100">
                <img :src="entry.logo_url" :alt="entry.brand ? `${entry.brand} logo` : ''" class="size-full object-contain p-1.5">
            </span>
            <span v-if="entry.brand" class="font-display text-section">{{ entry.brand }} garage</span>
            <span v-if="entry.vehicle" class="ms-8 inline-flex items-center gap-3 font-display text-section">
                <span class="inline-flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white ring-1 ring-neutral-100">
                    <Icon name="Car03Icon" class="size-6 text-neutral-500" />
                </span>
                {{ entry.vehicle }}
            </span>
        </div>

        <div v-if="location" class="space-y-3">
            <LocationMap
                :lat="location.lat"
                :lng="location.lng"
                :label="entry.station_name || location.address"
                color="var(--color-fuel)"
            />
            <div class="flex items-start justify-between gap-x-4 gap-y-1">
                <div v-if="entry.station_name || addressLine" class="min-w-0">
                    <p v-if="entry.station_name" class="text-meta font-medium text-neutral-900">{{ entry.station_name }}</p>
                    <p v-if="addressLine" class="text-meta text-neutral-600">{{ addressLine }}</p>
                </div>
                <ExternalLink :href="location.mapsUrl" label="View on Google Maps" class="shrink-0" />
            </div>
        </div>

        <StatGrid :stats="stats" />

        <DetailList v-if="hasDetails" :rows="details" />
    </div>
</template>
