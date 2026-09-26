<script setup>
import Eyebrow from './Eyebrow.vue';

/**
 * The embed card a scraper would build from a page's Open Graph tags: image,
 * host, title and description. Used to preview a share card before publishing.
 */
defineProps({
    image: { type: String, required: true },
    title: { type: String, default: null },
    description: { type: String, default: null },
    host: { type: String, default: null },
});
</script>

<template>
    <!-- Built as the embed itself rather than as a bare image: the
         title and description are what a reader actually judges the
         link on, and they come from the same tags the card does. -->
    <figure class="max-w-2xl overflow-hidden rounded-xl border border-neutral-50 bg-neutral-25">
        <!-- The box is reserved at the card's own ratio so opening
             this panel does not jump when the image arrives. -->
        <img
            :src="image"
            alt=""
            loading="lazy"
            class="block aspect-og w-full border-b border-neutral-50 bg-neutral-50 object-cover"
        >
        <figcaption class="space-y-1 p-4">
            <Eyebrow v-if="host" as="p" class="text-neutral-500">{{ host }}</Eyebrow>
            <p v-if="title" class="text-base font-semibold text-neutral-900">{{ title }}</p>
            <p v-if="description" class="text-sm text-neutral-500">{{ description }}</p>
        </figcaption>
    </figure>
</template>
