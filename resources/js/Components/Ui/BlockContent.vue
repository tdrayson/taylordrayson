<script setup>
import { computed, ref } from 'vue';
import PortableTextBlocks from './PortableTextBlocks.js';
import Lightbox from '../Overlays/Lightbox.vue';

// Read-only renderer for a Portable Text document. Accepts the bare node
// array (content is cast to an array server-side) or a raw JSON string.
const props = defineProps({
    document: { type: [Array, String], default: null },
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

// Every image in the document forms one lightbox gallery, opened in place.
const lightboxItems = computed(() =>
    nodes.value
        .filter((node) => node._type === 'image' && node.url)
        .map((node) => ({ full: node.url })),
);
const lightboxIndex = ref(null);

function openImage(url) {
    lightboxIndex.value = lightboxItems.value.findIndex((item) => item.full === url);
}
</script>

<template>
    <div v-if="nodes.length" class="block-content space-y-5 text-body text-neutral-900">
        <PortableTextBlocks :nodes="nodes" @image-click="openImage" />

        <Lightbox v-model:index="lightboxIndex" :photos="lightboxItems" />
    </div>
</template>

<style scoped>
.block-content :deep(a) {
    color: var(--color-accent-500);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.block-content :deep(a:hover) {
    color: var(--color-accent-700);
}

.block-content :deep(code) {
    border-radius: 4px;
    background: var(--color-neutral-25);
    padding: 0.1em 0.35em;
    font-size: 0.9em;
}

.block-content :deep(mark) {
    background: var(--color-accent-200);
    border-radius: 2px;
}
</style>
