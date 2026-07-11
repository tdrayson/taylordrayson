<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import { Film01Icon } from '@hugeicons-pro/core-stroke-rounded';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';
import ExternalLink from '../../Components/Ui/ExternalLink.vue';
import SectionHead from '../../Components/Ui/SectionHead.vue';
import SeriesStats from '../../Components/Ui/SeriesStats.vue';
import WatchDateGroup from '../../Components/Ui/WatchDateGroup.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // { slug, title, year, overview, poster, platformUrl }
    series: { type: Object, required: true },
    stats: { type: Object, required: true },
    // [{ season, dates: [{ date, anchor, episodes }] }]
    seasons: { type: Array, default: () => [] },
});

// The site's per-type accent colour (media = pink), matching the eyebrow
// styling on Archive.vue/Entry.vue for other timeline types.
const accentStyle = { color: 'var(--color-media)' };

setLayoutProps({
    breadcrumb: [
        { label: 'Media', href: '/media' },
        { label: 'TV', href: '/media/tv' },
        { label: props.series.title },
    ],
});
</script>

<template>
    <AppHead :og="{ title: series.title, heading: series.title, eyebrow: 'TV', accent: 'media', image: series.poster }" />

    <header class="flex flex-col gap-6 sm:flex-row sm:items-start">
        <div class="aspect-2/3 w-40 shrink-0 overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 sm:w-48">
            <img v-if="series.poster" :src="series.poster" alt="" class="size-full object-cover">
            <div v-else class="flex size-full items-center justify-center text-neutral-400">
                <Icon :icon="Film01Icon" class="size-10" />
            </div>
        </div>

        <div class="min-w-0 flex-1">
            <span class="text-eyebrow uppercase" :style="accentStyle">TV</span>
            <h1 class="mt-1 font-display text-display">{{ series.title }}</h1>
            <p v-if="series.year" class="mt-1 text-meta text-neutral-500">{{ series.year }}</p>
            <p v-if="series.overview" class="mt-4 max-w-prose text-body text-neutral-700">{{ series.overview }}</p>

            <div v-if="stats.progress !== null" class="mt-5 max-w-sm">
                <div class="flex items-center justify-between text-caption text-neutral-500">
                    <span>Progress</span>
                    <span class="tnum">{{ stats.progress }}%</span>
                </div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-neutral-50">
                    <div class="h-full rounded-full bg-accent-500" :style="{ width: `${stats.progress}%` }" />
                </div>
            </div>

            <ExternalLink v-if="series.platformUrl" :href="series.platformUrl" label="View on Trakt" class="mt-5" />
        </div>
    </header>

    <SeriesStats :stats="stats" class="mt-10" />

    <section v-for="seasonGroup in seasons" :key="seasonGroup.season" class="mt-4">
        <SectionHead :title="`Season ${seasonGroup.season}`" />

        <WatchDateGroup
            v-for="dateGroup in seasonGroup.dates"
            :key="dateGroup.anchor"
            :series-slug="series.slug"
            :anchor="dateGroup.anchor"
            :date="dateGroup.date"
            :episodes="dateGroup.episodes"
        />
    </section>

    <p v-if="!seasons.length" class="mt-10 text-meta text-neutral-500">No episodes watched yet.</p>
</template>
