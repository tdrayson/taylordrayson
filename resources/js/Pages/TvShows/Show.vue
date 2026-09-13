<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import { Film01Icon, StarIcon } from '@hugeicons-pro/core-stroke-rounded';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';
import ExternalLink from '../../Components/Ui/ExternalLink.vue';
import SectionHead from '../../Components/Ui/SectionHead.vue';
import TvShowStats from '../../Components/Ui/TvShowStats.vue';
import WatchDateGroup from '../../Components/Ui/WatchDateGroup.vue';
import EntryHero from '../../Components/Ui/EntryHero.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // { slug, title, year, overview, poster, backdrop, logo, network, rating, platformUrl }
    show: { type: Object, required: true },
    stats: { type: Object, required: true },
    // [{ season, dates: [{ date, anchor, episodes }] }]
    seasons: { type: Array, default: () => [] },
    // TMDB's season structure: [{ number, name, episodeCount, airDate }].
    seasonList: { type: Array, default: () => [] },
});

// The site's per-type accent colour (tv-episode = green), matching the eyebrow
// styling on Archive.vue/Entry.vue for other timeline types.
const accentStyle = { color: 'var(--color-tv-episode)' };

// The hero's logo names the show, so the heading stays for the outline but
// steps out of the way rather than printing the title twice.
const titleInHero = computed(() => Boolean(props.show.backdrop && props.show.logo));

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
        { label: 'TV', href: '/tv-shows' },
        { label: props.show.title },
    ],
});
</script>

<template>
    <AppHead :og="{ title: show.title, heading: show.title, eyebrow: 'TV', accent: 'tv-episode', image: show.backdrop || show.poster }" />

    <header class="flex flex-col gap-6 sm:flex-row sm:items-start">
        <!-- Without a backdrop there is no hero to carry the poster, so it sits
             beside the heading instead. -->
        <div v-if="! show.backdrop" class="aspect-2/3 w-40 shrink-0 overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 sm:w-48">
            <img v-if="show.poster" :src="show.poster" alt="" class="size-full object-cover">
            <div v-else class="flex size-full items-center justify-center text-neutral-400">
                <Icon :icon="Film01Icon" class="size-10" />
            </div>
        </div>

        <div class="min-w-0 flex-1">
            <span class="text-eyebrow uppercase" :style="accentStyle">TV</span>
            <h1 :class="titleInHero ? 'sr-only' : 'mt-1 max-w-2xl font-display text-display'">{{ show.title }}</h1>

            <div v-if="show.year || show.network" class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-meta text-neutral-500">
                <span v-if="show.year">{{ show.year }}</span>
                <span v-if="show.network">{{ show.network }}</span>
            </div>
        </div>
    </header>

    <EntryHero
        v-if="show.backdrop"
        :backdrop="show.backdrop"
        :logo="show.logo"
        :poster="show.poster"
        :title="show.title"
        class="mt-8"
    />

    <div v-if="show.rating" data-testid="tv-show-rating" class="mt-8 flex items-center gap-2">
        <Icon :icon="StarIcon" class="size-5 text-accent-500" />
        <span class="font-display text-stat tnum">{{ show.rating }}</span>
        <span class="text-meta text-neutral-500">/ 10</span>
    </div>

    <ExternalLink v-if="show.platformUrl" :href="show.platformUrl" label="View on Trakt" class="mt-6" />

    <TvShowStats :stats="stats" class="mt-10" />

    <p v-if="show.overview" class="mt-6 max-w-prose text-body text-neutral-700">{{ show.overview }}</p>

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
