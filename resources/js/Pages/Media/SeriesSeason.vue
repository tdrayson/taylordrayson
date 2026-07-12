<script setup>
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeriesStats from '../../Components/Ui/SeriesStats.vue';
import WatchDateGroup from '../../Components/Ui/WatchDateGroup.vue';
import BackdropHero from '../../Components/Ui/BackdropHero.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // { slug, title, backdrop, logo }
    series: { type: Object, required: true },
    season: { type: Number, required: true },
    // { episodesWatched, watchSpan, totalHours } - season-scoped, no seasons/progress.
    stats: { type: Object, required: true },
    // [{ date, anchor, episodes }]
    dates: { type: Array, default: () => [] },
});

const accentStyle = { color: 'var(--color-media)' };

setLayoutProps({
    breadcrumb: [
        { label: 'Media', href: '/media' },
        { label: 'TV', href: '/media/tv' },
        { label: props.series.title, href: `/media/tv/${props.series.slug}` },
        { label: `Season ${props.season}` },
    ],
});
</script>

<template>
    <AppHead :og="{ title: `${series.title}, Season ${season}`, heading: `Season ${season}`, eyebrow: series.title, accent: 'media', image: series.backdrop }" />

    <!-- Decorative only, no title overlay: the "Season N" heading below stays
         the page's single h1. -->
    <BackdropHero v-if="series.backdrop" testid="season-backdrop" :backdrop="series.backdrop" class="mb-8" />

    <header>
        <span class="text-eyebrow uppercase" :style="accentStyle">{{ series.title }}</span>
        <h1 class="mt-1 font-display text-display">Season {{ season }}</h1>
    </header>

    <SeriesStats :stats="stats" class="mt-8" />

    <div v-if="dates.length">
        <WatchDateGroup
            v-for="dateGroup in dates"
            :key="dateGroup.anchor"
            :series-slug="series.slug"
            :anchor="dateGroup.anchor"
            :date="dateGroup.date"
            :episodes="dateGroup.episodes"
        />
    </div>

    <p v-else class="mt-10 text-meta text-neutral-500">No episodes watched yet.</p>
</template>
