<script setup>
import { computed } from 'vue';
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';
import PosterCard from '../../Components/Ui/PosterCard.vue';
import { entryType } from '../../entryTypes.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // Newest-watched first (SeriesController::index()); each: { slug, title,
    // year, poster, progress }.
    series: { type: Array, default: () => [] },
});

// Reuse the media type's icon + accent so /media/tv's header matches /media.
const meta = entryType('media');
const accentStyle = { color: 'var(--color-media)' };

const subtitle = computed(() => `${props.series.length} ${props.series.length === 1 ? 'show' : 'shows'} watched`);

setLayoutProps({
    breadcrumb: [{ label: 'Media', href: '/media' }, { label: 'TV' }],
});
</script>

<template>
    <AppHead :og="{ title: 'TV', heading: 'TV', accent: 'media' }" />

    <header class="relative">
        <span class="absolute top-0 hidden size-12 shrink-0 items-center justify-center rounded-full bg-neutral-25 lg:-left-16 lg:flex" :style="accentStyle">
            <Icon :icon="meta.icon" class="size-6" />
        </span>
        <div class="min-w-0">
            <Link href="/media" class="text-eyebrow uppercase transition-colors hover:text-accent-500 focus-visible:text-accent-500" :style="accentStyle">Media</Link>
            <h1 class="mt-1 font-display text-display">TV</h1>
            <p v-if="subtitle" class="mt-2 text-meta text-neutral-500">{{ subtitle }}</p>
        </div>
    </header>

    <div v-if="series.length" class="mt-10 grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
        <PosterCard
            v-for="show in series"
            :key="show.slug"
            :slug="show.slug"
            :title="show.title"
            :year="show.year"
            :poster="show.poster"
            :progress="show.progress"
        />
    </div>

    <p v-else class="mt-10 text-meta text-neutral-500">No shows watched yet.</p>
</template>
