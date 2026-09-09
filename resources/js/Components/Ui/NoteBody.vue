<script setup>
import { computed, ref } from 'vue';
import PortableTextBlocks from './PortableTextBlocks.js';
import LinkPreviewLayer from './LinkPreviewLayer.vue';
import { useLinkContext } from '../../lib/linkContext.js';

// Two roots, so the class a caller passes still lands on the content element
// rather than on a wrapper around it.
defineOptions({ inheritAttrs: false });

// A note's own words in the feed, links and all.
const props = defineProps({
    document: { type: [Array, String], default: null },
});

// href -> preview for internal links, host -> favicon for external ones,
// provided by TimelineFeed from the cards it is drawing.
const links = useLinkContext();

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
        v-bind="$attrs"
        class="e-content mt-1.5 space-y-3 text-base leading-relaxed text-neutral-900"
    >
        <PortableTextBlocks :nodes="nodes" :favicons="links.favicons" :previews="links.previews" />
    </div>

    <!-- Outside the content element on purpose: a hover overlay is not part of
         what the author wrote, and its teleport anchors would otherwise sit in
         the e-content a microformats consumer reads back. -->
    <LinkPreviewLayer v-if="nodes.length" :previews="links.previews" :container="contentEl" />
</template>
