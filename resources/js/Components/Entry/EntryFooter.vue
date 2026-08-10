<script setup>
import { Link } from '@inertiajs/vue3';
import Source from '../Profile/Source.vue';

const props = defineProps({
    // The data source, e.g. { platform: 'swarm', url }, or null for first-party entries.
    source: { type: Object, default: null },
    // Linkable tags [{ name, slug }]; only taggable types (notes, articles, projects, events) carry any.
    tags: { type: Array, default: () => [] },
});

// The whole block collapses when an entry has neither tags nor a source, so
// untaggable, first-party entries render no empty rule.
const hasContent = () => props.tags.length > 0 || Boolean(props.source);
</script>

<template>
    <!-- Tags and source are independent lines, so a type gets whichever it has. -->
    <div v-if="hasContent()" class="space-y-2 border-t border-neutral-50 pt-4">
        <p v-if="tags.length" class="text-caption text-neutral-500">
            Tagged
            <template v-for="(tag, index) in tags" :key="tag.slug"><Link :href="`/tags/${tag.slug}`" class="font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500">#{{ tag.slug }}</Link><span v-if="index < tags.length - 1">, </span></template>
        </p>

        <Source v-if="source" :platform="source.platform" :url="source.url" />
    </div>
</template>
