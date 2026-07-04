<script setup>
import { computed } from 'vue';
import Pill from '../Ui/Pill.vue';
import BlockContent from '../Ui/BlockContent.vue';
import ContentToc from '../Ui/ContentToc.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

const tags = computed(() => (Array.isArray(props.entry.tags) ? props.entry.tags : []));

// Normalise the stored document (bare node array or JSON string) the same way
// BlockContent does, so heading count can be read straight from the data
// without waiting for anything to render.
const contentNodes = computed(() => {
    let doc = props.entry.content;

    if (typeof doc === 'string') {
        try {
            doc = JSON.parse(doc);
        } catch {
            return [];
        }
    }

    return Array.isArray(doc) ? doc : [];
});

// The renderer only stamps h2/h3 blocks with data-toc anchors, so the TOC is
// only worth showing once there are at least two of them to navigate between.
const headingCount = computed(() => contentNodes.value.filter(
    (node) => node?._type === 'block' && (node.style === 'h2' || node.style === 'h3'),
).length);
</script>

<template>
    <!-- relative + max-w-media anchors ContentToc's desktop rail in the gutter
         to the right of the widest (media) content block, mirroring how
         Story pages position StoryToc. -->
    <div class="relative max-w-media space-y-8">
        <div v-if="entry.draft || tags.length" class="flex flex-wrap gap-2">
            <Pill v-if="entry.draft" label="Draft" variant="accent" />
            <Pill v-for="tag in tags" :key="tag" :label="tag" />
        </div>

        <p v-if="entry.excerpt" class="max-w-reading text-body text-lg text-neutral-700">{{ entry.excerpt }}</p>

        <BlockContent :document="entry.content" />

        <!-- Mounted after BlockContent so its headings are already in the DOM
             when ContentToc's onMounted queries for them. -->
        <ContentToc v-if="headingCount >= 2" />
    </div>
</template>
