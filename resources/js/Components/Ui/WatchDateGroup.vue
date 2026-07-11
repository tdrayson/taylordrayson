<script setup>
import EpisodeRow from './EpisodeRow.vue';
import { dateLongFromYmd } from '../../lib/format.js';

defineProps({
    seriesSlug: { type: String, required: true },
    // Matches SeriesController::groupByWatchDate()'s "watch-{date}" anchor,
    // the target the collapsed timeline binge-card links to.
    anchor: { type: String, required: true },
    date: { type: String, required: true }, // Y-m-d
    episodes: { type: Array, default: () => [] },
});
</script>

<template>
    <section :id="anchor" class="mt-8 first:mt-0">
        <p class="text-caption font-medium text-neutral-500">{{ dateLongFromYmd(date) }}</p>
        <div class="mt-2 divide-y divide-neutral-50">
            <EpisodeRow
                v-for="episode in episodes"
                :key="episode.id"
                :series-slug="seriesSlug"
                :season="episode.season"
                :episode="episode.episode"
                :title="episode.title"
                :occurred-at="episode.occurredAt"
                :rating="episode.rating"
            />
        </div>
    </section>
</template>
