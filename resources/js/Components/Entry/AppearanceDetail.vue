<script setup>
import { computed } from 'vue';
import DetailList from '../DetailList.vue';
import SectionHead from '../SectionHead.vue';
import ExternalLink from '../ExternalLink.vue';
import { duration, titleCase } from '../../format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const rows = computed(() => [
    { label: 'Type', value: titleCase(props.entry.type) },
    { label: 'Show', value: props.entry.show_name },
    { label: 'Duration', value: duration(props.entry.duration) },
]);
</script>

<template>
    <div class="space-y-8">
        <DetailList :rows="rows" />

        <div v-if="entry.description">
            <SectionHead title="About" />
            <p class="text-body text-ink-2">{{ entry.description }}</p>
        </div>

        <div v-if="entry.url || entry.video_url" class="flex flex-wrap gap-x-6 gap-y-3">
            <ExternalLink v-if="entry.url" :href="entry.url" label="Show page" />
            <ExternalLink v-if="entry.video_url" :href="entry.video_url" label="Watch video" />
        </div>
    </div>
</template>
