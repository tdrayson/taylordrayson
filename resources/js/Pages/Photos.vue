<script setup>
import { ref } from 'vue';
import { setLayoutProps, Link } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import PhotoGrid from '../Components/Ui/PhotoGrid.vue';
import Lightbox from '../Components/Overlays/Lightbox.vue';
import { cn } from '../lib/cn.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    photos: { type: Array, default: () => [] },
    // Only present signed in; the bar renders from this, not from `filter`
    // (which is also null on "Everything"), so it can't render disabled for a
    // signed-out visitor.
    filter: { type: String, default: null },
    filters: { type: Array, default: null },
});

setLayoutProps({
    breadcrumb: [{ label: 'Photos' }],
});

const lightboxIndex = ref(null);

// Mirrors Life/Kind.vue's facetClasses(), so the two filter bars read as the
// same control.
function facetClasses(active) {
    return cn(
        'rounded-full px-3 py-1.5 text-meta font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
        active ? 'bg-accent-500 text-neutral-0' : 'bg-neutral-25 text-neutral-700 hover:bg-accent-50 hover:text-accent-700',
    );
}
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="font-display text-display">Photos</h1>
        <p class="mt-2 text-meta text-neutral-500">
            {{ photos.length }} photos from everything I've logged, newest first.
        </p>
    </header>

    <div v-if="filters" class="mt-6 flex flex-wrap gap-2">
        <Link
            v-for="option in filters"
            :key="option.value ?? 'everything'"
            :href="option.value ? `/photos?filter=${option.value}` : '/photos'"
            :class="facetClasses(filter === option.value)"
        >
            {{ option.label }} <span class="tnum">{{ option.count }}</span>
        </Link>
    </div>

    <PhotoGrid v-if="photos.length" :photos="photos" review class="mt-8" @open="lightboxIndex = $event" />
    <p v-else-if="filter" class="mt-8 text-meta text-neutral-500">Nothing needs this right now.</p>
    <p v-else class="mt-8 text-meta text-neutral-500">No photos yet.</p>

    <Lightbox v-model:index="lightboxIndex" :photos="photos" tags />
</template>
