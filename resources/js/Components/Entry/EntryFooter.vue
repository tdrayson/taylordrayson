<script setup>
import { Link } from '@inertiajs/vue3';
import Source from '../Profile/Source.vue';
import ExportMenu from './ExportMenu.vue';

const props = defineProps({
    // The data source, e.g. { platform: 'swarm', url }, or null for first-party entries.
    source: { type: Object, default: null },
    // Linkable tags [{ name, slug, url }]; only taggable types (notes, articles, projects, events) carry any.
    tags: { type: Array, default: () => [] },
    // [{ extension, type, label, purpose, url }] this entry can be exported as.
    formats: { type: Array, default: () => [] },
});

// The whole block collapses when an entry has neither tags, a source, nor any
// export formats, so a bare first-party entry renders no empty rule.
const hasContent = () => props.tags.length > 0 || Boolean(props.source) || props.formats.length > 0;
</script>

<template>
    <!-- Tags, source and formats are independent lines, so a type gets whichever it has. -->
    <div v-if="hasContent()" class="space-y-2 border-t border-neutral-50 pt-4">
        <p v-if="tags.length" class="text-xs text-neutral-500">
            Tagged
            <template v-for="(tag, index) in tags" :key="tag.slug"><Link :href="tag.url" class="font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500">{{ tag.name }}</Link><span v-if="index < tags.length - 1">, </span></template>
        </p>

        <Source v-if="source" :platform="source.platform" :url="source.url" />

        <ExportMenu :formats="formats" />
    </div>
</template>
