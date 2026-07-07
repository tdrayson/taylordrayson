<script setup>
import { computed, ref } from 'vue';
import DetailList from '../Ui/DetailList.vue';
import SectionHead from '../Ui/SectionHead.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import ActivityMedia from './ActivityMedia.vue';
import Lightbox from '../Overlays/Lightbox.vue';
import LocationMap from '../Maps/LocationMap.vue';
import { titleCase } from '../../lib/format.js';

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
const lightboxIndex = ref(null);

const rows = computed(() => [
    { label: 'Type', value: titleCase(props.entry.type) },
    { label: 'Organiser', value: props.entry.organiser },
    { label: 'Venue', value: props.entry.venue_name },
    { label: 'City', value: props.entry.city },
    { label: 'Country', value: props.entry.country },
    { label: 'Seat', value: seat.value },
]);
</script>

<template>
    <div class="space-y-8">
        <p v-if="range" class="inline-flex items-center gap-1.5 rounded-full bg-event/10 px-3 py-1 text-meta font-medium text-event">
            {{ range.label }} &middot; {{ range.days }} days
        </p>

        <ActivityMedia
            v-if="photos.length"
            :photos="photos"
            @open="lightboxIndex = $event"
        />
        <Lightbox v-model:index="lightboxIndex" :photos="photos" />

        <LocationMap v-if="location" :lat="location.lat" :lng="location.lng" :label="entry.venue_name || location.address" />

        <DetailList :rows="rows" />

        <div v-if="entry.description">
            <SectionHead title="Notes" />
            <p class="text-body text-neutral-700">{{ entry.description }}</p>
        </div>

        <div v-if="location">
            <ExternalLink :href="location.mapsUrl" label="View on Google Maps" />
        </div>

        <div v-if="entry.url">
            <ExternalLink :href="entry.url" label="More about this event" />
        </div>
    </div>
</template>
