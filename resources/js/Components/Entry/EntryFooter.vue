<script setup>
import Source from '../Profile/Source.vue';
import Pill from '../Ui/Pill.vue';

const props = defineProps({
    // The data source, e.g. { platform: 'swarm', url }, or null for first-party entries.
    source: { type: Object, default: null },
    // Linkable tags [{ name, slug }]; only taggable types (notes, articles, projects, events) carry any.
    tags: { type: Array, default: () => [] },
});

// The whole footer collapses when an entry has neither a source nor tags, so
// untaggable, first-party entries render no empty rule. Mirrors the truthiness
// of the source row's own v-if so the two never disagree.
const hasContent = () => Boolean(props.source) || props.tags.length > 0;
</script>

<template>
    <!-- One shared metadata row for every entry type: source anchored left,
         tags on the opposite edge. `ml-auto` pushes the tag block right, and
         `justify-end` keeps wrapped tag rows against that same edge. A plain
         div, not <footer>, so it never competes with the page's real footer
         landmark. -->
    <div v-if="hasContent()" class="flex flex-wrap items-center gap-x-4 gap-y-3 border-t border-neutral-50 pt-4">
        <Source v-if="source" :platform="source.platform" :url="source.url" />

        <ul v-if="tags.length" class="ml-auto flex flex-wrap justify-end gap-2">
            <li v-for="tag in tags" :key="tag.slug">
                <Pill :label="tag.name" :href="`/tags/${tag.slug}`" />
            </li>
        </ul>
    </div>
</template>
