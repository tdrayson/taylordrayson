<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';
import PosterCard from '../../Components/Ui/PosterCard.vue';
import { entryType } from '../../entryTypes.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // Newest-watched first (WatchedTvShowsIndex); each: { slug, title, year,
    // poster, progress }.
    shows: { type: Array, default: () => [] },
});

// The tv-episode type's icon + accent, matching the eyebrow styling other
// top-level archive pages use for their own type.
const meta = entryType('tv-episode');
const accentStyle = { color: 'var(--color-tv-episode)' };

const subtitle = computed(() => `${props.shows.length} ${props.shows.length === 1 ? 'show' : 'shows'} watched`);

setLayoutProps({
    breadcrumb: [{ label: 'TV' }],
});
</script>

<template>
    <AppHead :og="{ title: 'TV shows', heading: 'TV shows', accent: 'tv-episode' }" />

    <header class="relative">
        <span class="absolute top-0 hidden size-12 shrink-0 items-center justify-center rounded-full bg-neutral-25 lg:-left-16 lg:flex" :style="accentStyle">
            <Icon :icon="meta.icon" class="size-6" />
        </span>
        <div class="min-w-0">
            <h1 class="font-display text-display">TV shows</h1>
            <p v-if="subtitle" class="mt-2 text-meta text-neutral-500">{{ subtitle }}</p>
        </div>
    </header>

    <div v-if="shows.length" class="mt-10 grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
        <PosterCard
            v-for="show in shows"
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
