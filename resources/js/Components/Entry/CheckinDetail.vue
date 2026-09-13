<script setup>
import { computed, ref } from 'vue';
import Pill from '../Ui/Pill.vue';
import LocationMap from '../Maps/LocationMap.vue';
import ActivityMedia from './ActivityMedia.vue';
import Lightbox from '../Overlays/Lightbox.vue';
import ExternalLink from '../Ui/ExternalLink.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

// Coordinate object the controller attaches for located entries; null otherwise.
const location = computed(() => props.entry.location ?? null);

// Full street address shown under the venue name (fuel-page convention), with
// the venue name itself omitted since it's the bold line above; blanks dropped.
const addressLine = computed(() =>
    [props.entry.address, props.entry.city, props.entry.county, props.entry.country]
        .filter(Boolean)
        .join(', '),
);

// Swarm photo gallery in {src,srcset,full} shape; empty when the check-in has none.
const photos = computed(() => (Array.isArray(props.entry.photos) ? props.entry.photos : []));

// Which photo the lightbox is showing (null = closed).
const lightboxIndex = ref(null);
</script>

<template>
    <div class="space-y-8">
        <p v-if="entry.description" v-twemoji class="text-balance whitespace-pre-line text-neutral-700">
            {{ entry.description }}
        </p>

        <div v-if="entry.category">
            <Pill :label="entry.category" :href="entry.categoryHref" variant="outline" />
        </div>

        <div v-if="location" class="space-y-3">
            <LocationMap
                :lat="location.lat"
                :lng="location.lng"
                :label="entry.venue_name || location.address"
                color="var(--color-checkin)"
            />
            <div class="flex flex-col gap-y-2 sm:flex-row sm:items-start sm:justify-between sm:gap-x-4 sm:gap-y-1">
                <div v-if="entry.venue_name || addressLine" class="min-w-0">
                    <p v-if="entry.venue_name" class="text-meta font-medium text-neutral-900">{{ entry.venue_name }}</p>
                    <p v-if="addressLine" class="text-meta text-neutral-600">{{ addressLine }}</p>
                </div>
                <ExternalLink :href="location.mapsUrl" label="View on Google Maps" class="shrink-0" />
            </div>
        </div>

        <ActivityMedia
            v-if="photos.length"
            :photos="photos"
            @open="lightboxIndex = $event"
        />
        <Lightbox v-model:index="lightboxIndex" :photos="photos" tags />
    </div>
</template>
