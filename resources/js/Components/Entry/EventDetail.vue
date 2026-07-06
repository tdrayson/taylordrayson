<script setup>
import { computed } from 'vue';
import DetailList from '../Ui/DetailList.vue';
import SectionHead from '../Ui/SectionHead.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import { titleCase } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

// Loose, display-only details live in the meta JSON column (seat, geocoding extras).
const seat = computed(() => props.entry.meta?.seat ?? null);

const rows = computed(() => [
    { label: 'Type', value: titleCase(props.entry.type) },
    { label: 'Company', value: props.entry.company },
    { label: 'Venue', value: props.entry.venue_name },
    { label: 'City', value: props.entry.city },
    { label: 'Country', value: props.entry.country },
    { label: 'Seat', value: seat.value },
]);
</script>

<template>
    <div class="space-y-8">
        <DetailList :rows="rows" />

        <div v-if="entry.description">
            <SectionHead title="Notes" />
            <p class="text-body text-neutral-700">{{ entry.description }}</p>
        </div>

        <div v-if="entry.url">
            <ExternalLink :href="entry.url">More about this event</ExternalLink>
        </div>
    </div>
</template>
