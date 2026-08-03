<script setup>
import { computed, ref } from 'vue';
import SectionHead from '../Ui/SectionHead.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import ActivityMedia from './ActivityMedia.vue';
import Lightbox from '../Overlays/Lightbox.vue';
import LocationMap from '../Maps/LocationMap.vue';
import StatGrid from '../Stats/StatGrid.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

// Loose, display-only details live in the meta JSON column (seat, geocoding extras).
const seat = computed(() => props.entry.meta?.seat ?? null);
// Photo gallery in {src,srcset,full} shape; empty when the event has no photos.
const photos = computed(() => (Array.isArray(props.entry.photos) ? props.entry.photos : []));
const location = computed(() => props.entry.location ?? null);
// Multi-day range badge data, null for single-day events.
const range = computed(() => props.entry.range ?? null);
// Organiser is only worth showing when it adds something beyond the event name.
const organiser = computed(() => {
    const value = props.entry.organiser;

    return value && value !== props.entry.name ? value : null;
});
// Short categorical facts, shown as display-figure stats rather than a table. Blanks are dropped by StatGrid.
const facts = computed(() =>
    [
        { label: 'Organiser', value: organiser.value },
        { label: 'Seat', value: seat.value },
    ].filter((fact) => fact.value),
);
const lightboxIndex = ref(null);
</script>

<template>
    <div class="space-y-8">
        <!-- The event category now renders as a tag in the shared footer, so it
             is no longer repeated as a pill here. -->
        <p v-if="range" class="text-meta text-neutral-500">
            {{ range.long }} ({{ range.days }} days)
        </p>

        <div v-if="location" class="space-y-3">
            <LocationMap
                :lat="location.lat"
                :lng="location.lng"
                :label="entry.venue_name || location.address"
                color="var(--color-event)"
            />
            <div class="flex flex-col gap-y-2 sm:flex-row sm:flex-wrap sm:items-baseline sm:justify-between sm:gap-x-4 sm:gap-y-1">
                <p class="text-meta text-neutral-600">
                    <span v-if="entry.venue_name" class="font-medium text-neutral-900">{{ entry.venue_name }}</span><span v-if="entry.city">{{ entry.venue_name ? ', ' : '' }}{{ entry.city }}</span><span v-if="entry.country">, {{ entry.country }}</span>
                </p>
                <ExternalLink :href="location.mapsUrl" label="View on Google Maps" />
            </div>
        </div>

        <ActivityMedia
            v-if="photos.length"
            :photos="photos"
            @open="lightboxIndex = $event"
        />
        <Lightbox v-model:index="lightboxIndex" :photos="photos" />

        <StatGrid v-if="facts.length" :stats="facts" />

        <div v-if="entry.description">
            <SectionHead title="Notes" />
            <p v-twemoji class="text-body text-neutral-700">{{ entry.description }}</p>
        </div>

        <div v-if="entry.url">
            <ExternalLink :href="entry.url" label="More about this event" />
        </div>
    </div>
</template>
