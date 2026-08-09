<script setup>
import { computed, ref } from 'vue';
import PortableTextBlocks from './PortableTextBlocks.js';
import Lightbox from '../Overlays/Lightbox.vue';
import LinkPreviewLayer from './LinkPreviewLayer.vue';

// Read-only renderer for a Portable Text document. Accepts the bare node
// array (content is cast to an array server-side) or a raw JSON string.
const props = defineProps({
    document: { type: [Array, String], default: null },
    // Map of href -> preview data for internal content links (page prop from
    // the entry/page controller), forwarded to LinkPreviewLayer.
    linkPreviews: { type: Object, default: () => ({}) },
});

const nodes = computed(() => {
    let doc = props.document;

    if (typeof doc === 'string') {
        try {
            doc = JSON.parse(doc);
        } catch {
            return [];
        }
    }

    return Array.isArray(doc) ? doc : [];
});

// The clicked image opens alone: article images are standalone illustrations,
// not a connected gallery, so the lightbox gets no prev/next between them.
// The node's caption still carries through to the overlay.
const activeImage = ref(null);
const lightboxIndex = ref(null);

function openImage(url) {
    const node = nodes.value.find((item) => item._type === 'image' && item.url === url);

    activeImage.value = { full: url, caption: node?.caption || null };
    lightboxIndex.value = 0;
}

// LinkPreviewLayer queries this element's rendered <a> tags on mount, after
// PortableTextBlocks has turned the Portable Text link marks into real anchors.
const contentEl = ref(null);
</script>

<template>
    <!-- prose supplies the inter-element rhythm; its :where() selectors have zero
         specificity, so the renderer's explicit classes always win. -->
    <div v-if="nodes.length" ref="contentEl" v-twemoji class="block-content prose max-w-none text-body text-neutral-900">
        <PortableTextBlocks :nodes="nodes" @image-click="openImage" />

        <Lightbox v-model:index="lightboxIndex" :photos="activeImage ? [activeImage] : []" />

        <LinkPreviewLayer :previews="linkPreviews" :container="contentEl" />
    </div>
</template>

<style scoped>
/* Unbroken strings (polylines, long tokens, URLs) wrap instead of overflowing
   the column. Inherited everywhere; <pre> is unaffected (no soft wrap + its
   own horizontal scroll). */
.block-content {
    overflow-wrap: anywhere;
}

.block-content :deep(a) {
    color: var(--color-accent-500);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.block-content :deep(a:hover) {
    color: var(--color-accent-700);
}

/* Inline-code chips only — code inside <pre> belongs to CodeBlock's own styling. */
.block-content :deep(:not(pre) > code) {
    border-radius: 4px;
    background: var(--color-neutral-25);
    padding: 0.1em 0.35em;
    font-size: 0.9em;
}

/* The typography plugin wraps inline code in literal backtick pseudo-elements;
   the chip background already marks it as code. */
.block-content :deep(code)::before,
.block-content :deep(code)::after {
    content: none;
}

.block-content :deep(mark) {
    background: var(--color-accent-200);
    border-radius: 2px;
}
</style>
