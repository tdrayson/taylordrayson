<script setup>
import { computed } from 'vue';
import Pill from '../Ui/Pill.vue';
import BlockContent from '../Ui/BlockContent.vue';
import TableOfContents from '../Ui/TableOfContents.vue';

const props = defineProps({
    entry: { type: Object, required: true },
    // Map of href -> preview data for internal content links, forwarded to BlockContent.
    linkPreviews: { type: Object, default: () => ({}) },
});

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
    <!-- Re-establishes the content grid so the cover can bleed full width while
         everything else stays in the content column. -->
    <div class="full-width content-grid gap-y-8">
        <div v-if="!entry.published" class="flex flex-wrap gap-2">
            <Pill label="Draft" variant="accent" />
        </div>

        <!-- The wrapper, not the img, is the grid item: replaced elements don't
             stretch to their grid area, block boxes do. -->
        <div v-if="entry.cover" class="mb-6 aspect-video overflow-hidden border-y border-neutral-50 full-width md:rounded-lg md:border-x md:breakout">
            <img
                :src="entry.cover.src"
                :srcset="entry.cover.srcset || undefined"
                sizes="100vw"
                alt=""
                class="size-full object-cover"
            >
        </div>

        <!-- Anchors the TableOfContents rail in the gutter. Wraps only the body so
             the rail's top-0 lines up with the first line of prose. -->
        <div class="relative max-w-media space-y-8">
            <p v-if="entry.excerpt" v-twemoji class="max-w-prose text-body text-lg text-neutral-700">{{ entry.excerpt }}</p>

            <BlockContent :document="entry.content" :link-previews="linkPreviews" />

            <!-- Mounted after BlockContent so its headings are already in the DOM
                 when TableOfContents's onMounted queries for them. -->
            <TableOfContents v-if="headingCount >= 2" />
        </div>
    </div>
</template>
