<script setup>
import { computed, ref } from 'vue';
import { setLayoutProps, InfiniteScroll } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import PhotoGrid from '../Components/Ui/PhotoGrid.vue';
import PhotoGridSkeleton from '../Components/Ui/PhotoGridSkeleton.vue';
import Lightbox from '../Components/Overlays/Lightbox.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    total: { type: Number, default: 0 },
    // A paginator: photos land in `data` and grow as pages are appended. Absent
    // until the deferred first page arrives.
    photos: { type: Object, default: null },
});

setLayoutProps({
    breadcrumb: [{ label: 'Photos' }],
});

const photos = computed(() => props.photos?.data ?? []);

const lightboxIndex = ref(null);
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="font-display text-display">Photos</h1>
        <p class="mt-2 text-meta text-neutral-500">
            {{ total }} photos from everything I've logged, newest first.
        </p>
    </header>

    <div class="mt-8">
        <!-- The first page is deferred, so the heading paints while it loads. -->
        <PhotoGridSkeleton v-if="!props.photos" />

        <InfiniteScroll v-else-if="total" data="photos" only-next>
            <PhotoGrid :photos="photos" @open="lightboxIndex = $event" />

            <template #loading>
                <PhotoGridSkeleton :count="4" class="mt-3" />
            </template>
        </InfiniteScroll>

        <p v-else class="text-meta text-neutral-500">No photos yet.</p>
    </div>

    <Lightbox v-model:index="lightboxIndex" :photos="photos" />
</template>
