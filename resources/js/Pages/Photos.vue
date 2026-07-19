<script setup>
import { ref } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import PhotoGrid from '../Components/Ui/PhotoGrid.vue';
import Lightbox from '../Components/Overlays/Lightbox.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    photos: { type: Array, default: () => [] },
});

setLayoutProps({
    breadcrumb: [{ label: 'Photos' }],
});

const lightboxIndex = ref(null);
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="font-display text-display">Photos</h1>
        <p class="mt-2 text-meta text-neutral-500">
            {{ photos.length }} photos from everything I've logged, newest first.
        </p>
    </header>

    <PhotoGrid v-if="photos.length" :photos="photos" class="mt-8" @open="lightboxIndex = $event" />
    <p v-else class="mt-8 text-meta text-neutral-500">No photos yet.</p>

    <Lightbox v-model:index="lightboxIndex" :photos="photos" />
</template>
