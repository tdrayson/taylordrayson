<script setup>
import { computed } from 'vue';
import DetailList from '../Ui/DetailList.vue';
import SectionHead from '../Ui/SectionHead.vue';
import { number, titleCase } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const rows = computed(() => [
    { label: 'Type', value: titleCase(props.entry.type) },
    { label: 'Venue', value: props.entry.venue_name },
    { label: 'Address', value: props.entry.address },
    { label: 'City', value: props.entry.city },
    { label: 'Country', value: props.entry.country },
    { label: 'Ticket price', value: props.entry.ticket_price ? `£${number(props.entry.ticket_price, 2)}` : null },
]);
</script>

<template>
    <div class="space-y-8">
        <DetailList :rows="rows" />

        <div v-if="entry.notes">
            <SectionHead title="Notes" />
            <p class="text-body text-neutral-700">{{ entry.notes }}</p>
        </div>
    </div>
</template>
