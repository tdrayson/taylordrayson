<script setup>
import SectionHead from '../Ui/SectionHead.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import Pill from '../Ui/Pill.vue';
import { titleCase } from '../../lib/format.js';

defineProps({
    entry: { type: Object, required: true },
});
</script>

<template>
    <div class="space-y-8">
        <div class="flex flex-wrap items-center gap-2">
            <Pill v-if="entry.status" :label="titleCase(entry.status)" :variant="entry.status === 'active' ? 'accent' : 'default'" />
            <Pill v-if="entry.featured" label="Featured" />
        </div>

        <p v-if="entry.description" v-twemoji class="text-body text-neutral-700">{{ entry.description }}</p>

        <div v-if="entry.long_description">
            <SectionHead title="About" />
            <p v-twemoji class="whitespace-pre-line text-body text-neutral-700">{{ entry.long_description }}</p>
        </div>

        <div v-if="entry.url || entry.github_url" class="flex flex-wrap gap-x-6 gap-y-3">
            <ExternalLink v-if="entry.url" :href="entry.url" label="Visit site" />
            <ExternalLink v-if="entry.github_url" :href="entry.github_url" :label="`View ${entry.title} on GitHub`" />
        </div>
    </div>
</template>
