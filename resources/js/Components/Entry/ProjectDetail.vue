<script setup>
import { computed } from 'vue';
import SectionHead from '../SectionHead.vue';
import ExternalLink from '../ExternalLink.vue';
import Pill from '../Pill.vue';
import { titleCase } from '../../format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const tags = computed(() => (Array.isArray(props.entry.tags) ? props.entry.tags : []));
</script>

<template>
    <div class="space-y-8">
        <div class="flex flex-wrap items-center gap-2">
            <Pill v-if="entry.status" :label="titleCase(entry.status)" :accent="entry.status === 'active'" />
            <Pill v-if="entry.featured" label="Featured" />
        </div>

        <p v-if="entry.description" class="text-body text-ink-2">{{ entry.description }}</p>

        <div v-if="entry.long_description">
            <SectionHead title="About" />
            <p class="whitespace-pre-line text-body text-ink-2">{{ entry.long_description }}</p>
        </div>

        <div v-if="tags.length" class="flex flex-wrap gap-2">
            <Pill v-for="tag in tags" :key="tag" :label="tag" />
        </div>

        <div v-if="entry.url || entry.github_url" class="flex flex-wrap gap-x-6 gap-y-3">
            <ExternalLink v-if="entry.url" :href="entry.url" label="Visit site" />
            <ExternalLink v-if="entry.github_url" :href="entry.github_url" label="GitHub" />
        </div>
    </div>
</template>
