<script setup>
import { computed } from 'vue';
import DetailList from '../Ui/DetailList.vue';
import SectionHead from '../Ui/SectionHead.vue';
import Pill from '../Ui/Pill.vue';
import LocationMap from '../Maps/LocationMap.vue';
import ExternalLink from '../Ui/ExternalLink.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

const rows = computed(() => [
    { label: 'Category', value: props.entry.category },
    { label: 'Address', value: props.entry.address },
    { label: 'City', value: props.entry.city },
    { label: 'County', value: props.entry.county },
    { label: 'Country', value: props.entry.country },
]);

// Coordinate object the controller attaches for located entries; null otherwise.
const location = computed(() => props.entry.location ?? null);
</script>

<template>
    <div class="space-y-8">
        <div v-if="entry.is_mayor">
            <Pill label="Mayor" variant="accent" />
        </div>

        <div v-if="location" class="space-y-3">
            <LocationMap
                :lat="location.lat"
                :lng="location.lng"
                :label="entry.venue_name || location.address"
                color="var(--color-checkin)"
            />
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <p class="text-meta text-neutral-600">
                    <span v-if="entry.venue_name" class="font-medium text-neutral-900">{{ entry.venue_name }}</span><span v-if="entry.city">{{ entry.venue_name ? ', ' : '' }}{{ entry.city }}</span>
                </p>
                <ExternalLink :href="location.mapsUrl" label="View on Google Maps" />
            </div>
        </div>

        <DetailList :rows="rows" />

        <div v-if="entry.description">
            <SectionHead title="Note" />
            <p class="text-body text-neutral-700">{{ entry.description }}</p>
        </div>
    </div>
</template>
