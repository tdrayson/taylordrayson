<script setup>
import { ref, computed } from 'vue';
import BlockContent from '../Ui/BlockContent.vue';
import ZoomButton from '../Ui/ZoomButton.vue';
import Lightbox from '../Overlays/Lightbox.vue';

const props = defineProps({
    entry: { type: Object, required: true },
    // Map of href -> preview card for internal links, forwarded to the renderer.
    linkPreviews: { type: Object, default: () => ({}) },
});

const photos = computed(() => (Array.isArray(props.entry.photos) ? props.entry.photos : []));

// Which photo the lightbox is showing (null = closed).
const lightboxIndex = ref(null);
</script>

<template>
    <div class="max-w-prose space-y-4">
        <!-- Notes have no headline, so the content itself is the page's primary
             text. Portable Text since notes gained mentions, so it renders
             through the same component as pages and articles. -->
        <BlockContent :document="entry.content" :link-previews="linkPreviews" class="text-lg leading-relaxed" />

        <!-- A single photo runs full width; small galleries share a grid. -->
        <ul v-if="photos.length" :class="photos.length > 1 ? 'grid grid-cols-2 gap-2.5' : ''">
            <li v-for="(photo, index) in photos" :key="index">
                <button
                    type="button"
                    :aria-label="`View photo ${index + 1}`"
                    class="group/zoom relative block w-full overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 transition-opacity hover:opacity-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    :class="photos.length > 1 ? 'aspect-square' : ''"
                    @click="lightboxIndex = index"
                >
                    <img :src="photo.src" :srcset="photo.srcset || undefined" sizes="(min-width: 768px) 608px, 100vw" alt="" class="size-full object-cover">
                    <span class="pointer-events-none absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100">
                        <ZoomButton />
                    </span>
                </button>
            </li>
        </ul>

        <Lightbox v-model:index="lightboxIndex" :photos="photos" />
    </div>
</template>
