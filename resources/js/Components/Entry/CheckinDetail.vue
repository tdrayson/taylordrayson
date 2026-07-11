<script setup>
import { computed } from 'vue';
import DetailList from '../Ui/DetailList.vue';
import SectionHead from '../Ui/SectionHead.vue';
import Pill from '../Ui/Pill.vue';

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
</script>

<template>
    <div class="space-y-8">
        <div v-if="entry.is_mayor">
            <Pill label="Mayor" variant="accent" />
        </div>

        <DetailList :rows="rows" />

        <div v-if="entry.description">
            <SectionHead title="Note" />
            <p v-twemoji class="text-body text-neutral-700">{{ entry.description }}</p>
        </div>
    </div>
</template>
