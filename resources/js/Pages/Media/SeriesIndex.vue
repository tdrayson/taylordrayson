<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ViewHeader from '../../Components/Layout/ViewHeader.vue';
import PosterCard from '../../Components/Ui/PosterCard.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // Newest-watched first (SeriesController::index()); each: { slug, title,
    // year, poster, progress }.
    series: { type: Array, default: () => [] },
});

const subtitle = computed(() => `${props.series.length} ${props.series.length === 1 ? 'show' : 'shows'} watched`);

setLayoutProps({
    breadcrumb: [{ label: 'Media', href: '/media' }, { label: 'TV' }],
});
</script>

<template>
    <AppHead :og="{ title: 'TV', heading: 'TV', accent: 'media' }" />

    <ViewHeader title="TV" :subtitle="subtitle" />

    <div v-if="series.length" class="mt-10 grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 lg:grid-cols-5">
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
