<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import { Film01Icon, StarIcon } from '@hugeicons-pro/core-stroke-rounded';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';
import ExternalLink from '../../Components/Ui/ExternalLink.vue';
import SectionHead from '../../Components/Ui/SectionHead.vue';
import SeriesStats from '../../Components/Ui/SeriesStats.vue';
import WatchDateGroup from '../../Components/Ui/WatchDateGroup.vue';
import MediaHero from '../../Components/Ui/MediaHero.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // { slug, title, year, overview, poster, backdrop, logo, network, rating, platformUrl }
    series: { type: Object, required: true },
    stats: { type: Object, required: true },
    // [{ season, dates: [{ date, anchor, episodes }] }]
    seasons: { type: Array, default: () => [] },
    // TMDB's season structure: [{ number, name, episodeCount, airDate }].
    seasonList: { type: Array, default: () => [] },
});

// The site's per-type accent colour (media = pink), matching the eyebrow
// styling on Archive.vue/Entry.vue for other timeline types.
const accentStyle = { color: 'var(--color-media)' };

// The hero's logo names the series, so the heading stays for the outline but
// steps out of the way rather than printing the title twice.
const titleInHero = computed(() => Boolean(props.series.backdrop && props.series.logo));

// "8 episodes, 2022" (or just one part when the other's missing) for a
// season overview tile; never both null since seasonList only ever carries
// entries TMDB actually returned data for.
function seasonMeta(season) {
    const parts = [];

    if (season.episodeCount) {
        parts.push(`${season.episodeCount} episode${season.episodeCount === 1 ? '' : 's'}`);
    }

    const year = season.airDate ? new Date(season.airDate).getFullYear() : null;
    if (year) {
        parts.push(year);
    }

    return parts.join(', ');
}

setLayoutProps({
    breadcrumb: [
        { label: 'Media', href: '/media' },
        { label: 'TV series', href: '/media/tv' },
        { label: props.series.title },
    ],
});
</script>

<template>
    <AppHead :og="{ title: series.title, heading: series.title, eyebrow: 'TV series', accent: 'media', image: series.backdrop || series.poster }" />

    <header class="flex flex-col gap-6 sm:flex-row sm:items-start">
        <!-- Without a backdrop there is no hero to carry the poster, so it sits
             beside the heading instead. -->
        <div v-if="! series.backdrop" class="aspect-2/3 w-40 shrink-0 overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 sm:w-48">
            <img v-if="series.poster" :src="series.poster" alt="" class="size-full object-cover">
            <div v-else class="flex size-full items-center justify-center text-neutral-400">
                <Icon :icon="Film01Icon" class="size-10" />
            </div>
        </div>

        <div class="min-w-0 flex-1">
            <span class="text-eyebrow uppercase" :style="accentStyle">TV series</span>
            <h1 :class="titleInHero ? 'sr-only' : 'mt-1 max-w-2xl font-display text-display'">{{ series.title }}</h1>

            <div v-if="series.year || series.network" class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-meta text-neutral-500">
                <span v-if="series.year">{{ series.year }}</span>
                <span v-if="series.network">{{ series.network }}</span>
            </div>
        </div>
    </header>

    <MediaHero
        v-if="series.backdrop"
        :backdrop="series.backdrop"
        :logo="series.logo"
        :poster="series.poster"
        :title="series.title"
        class="mt-8"
    />

    <div v-if="series.rating" data-testid="series-rating" class="mt-8 flex items-center gap-2">
        <Icon :icon="StarIcon" class="size-5 text-accent-500" />
        <span class="font-display text-stat tnum">{{ series.rating }}</span>
        <span class="text-meta text-neutral-500">/ 10</span>
    </div>

    <ExternalLink v-if="series.platformUrl" :href="series.platformUrl" label="View on Trakt" class="mt-6" />

    <SeriesStats :stats="stats" class="mt-10" />

    <p v-if="series.overview" class="mt-6 max-w-prose text-body text-neutral-700">{{ series.overview }}</p>

    <section v-if="seasonList.length" data-testid="season-overview" class="mt-4">
        <SectionHead title="Seasons" />

        <div class="flex flex-wrap gap-3">
            <div v-for="item in seasonList" :key="item.number" class="w-36 rounded-lg border border-neutral-50 bg-neutral-25 p-3">
                <p class="truncate text-meta font-medium text-neutral-900">{{ item.name || `Season ${item.number}` }}</p>
                <p v-if="seasonMeta(item)" class="mt-0.5 text-caption text-neutral-500">{{ seasonMeta(item) }}</p>
            </div>
        </div>
    </section>

    <section v-for="seasonGroup in seasons" :key="seasonGroup.season" class="mt-4">
        <SectionHead :title="`Season ${seasonGroup.season}`" />

        <WatchDateGroup
            v-for="dateGroup in seasonGroup.dates"
            :key="dateGroup.anchor"
            :anchor="dateGroup.anchor"
            :date="dateGroup.date"
            :episodes="dateGroup.episodes"
        />
    </section>

    <p v-if="!seasons.length" class="mt-10 text-meta text-neutral-500">No episodes watched yet.</p>
</template>
