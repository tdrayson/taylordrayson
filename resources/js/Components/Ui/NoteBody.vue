<script setup>
import { computed, ref } from 'vue';
import PortableTextBlocks from './PortableTextBlocks.js';
import LinkPreviewLayer from './LinkPreviewLayer.vue';

// A note's own words in the feed, links and all. Unlike BlockContent this takes
// its link data as props rather than from page context: the card is built by
// NoteCard, so every feed surface gets previews without each page providing
// them.
const props = defineProps({
    document: { type: [Array, String], default: null },
    // host -> stored favicon URL, for external link chips.
    favicons: { type: Object, default: () => ({}) },
    // internal href -> preview card, for the hover layer below.
    previews: { type: Object, default: () => ({}) },
});

// Text blocks and lists only. The notes API accepts any Portable Text document,
// and a feed card is no place for a video embed or a code block.
const nodes = computed(() => {
    let doc = props.document;

    if (typeof doc === 'string') {
        try {
            doc = JSON.parse(doc);
        } catch {
            return [];
        }
    }

    return Array.isArray(doc) ? doc.filter((node) => node._type === 'block') : [];
});

// LinkPreviewLayer reads this element's rendered <a> tags on mount, once
// PortableTextBlocks has turned the link marks into real anchors.
const contentEl = ref(null);
</script>

<template>
    <div
        v-if="nodes.length"
        ref="contentEl"
        v-twemoji
        class="e-content mt-1.5 space-y-3 text-base leading-relaxed text-neutral-900"
    >
        <PortableTextBlocks :nodes="nodes" :favicons="favicons" :previews="previews" />

        <LinkPreviewLayer :previews="previews" :container="contentEl" />
    </div>
</template>
