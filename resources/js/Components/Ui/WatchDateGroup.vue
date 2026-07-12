<script setup>
import EpisodeRow from './EpisodeRow.vue';
import { dateLongFromYmd } from '../../lib/format.js';

defineProps({
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
        <div class="mt-2">
            <EpisodeRow
                v-for="episode in episodes"
                :key="episode.id"
                :season="episode.season"
                :episode="episode.episode"
                :title="episode.title"
                :occurred-at="episode.occurredAt"
                :rating="episode.rating"
                :url="episode.url"
            />
        </div>
    </section>
</template>
