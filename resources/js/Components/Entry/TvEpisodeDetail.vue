<script setup>
import { computed } from 'vue';
import DetailList from '../Ui/DetailList.vue';
import Icon from '../Ui/Icon.vue';
import EntryHero from '../Ui/EntryHero.vue';
import Pill from '../Ui/Pill.vue';
import Stat from '../Ui/Stat.vue';
import { number, titleCase } from '../../lib/format.js';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    entry: { type: Object, required: true },
});

const { measure, exactMeasure } = useFormat();

const meta = computed(() => props.entry.meta || {});
const runtime = computed(() => (meta.value.runtime ? `${number(meta.value.runtime)} min` : null));

// TMDB enrichment stores genres under meta.tmdb.genres; fall back to a
// top-level meta.genres for any legacy/other source.
const genres = computed(() => {
    const source = meta.value.tmdb?.genres ?? meta.value.genres;
    return Array.isArray(source) ? source : [];
});

const rows = computed(() => [
    { label: 'Type', value: titleCase(props.entry.type) },
    // showTitle prefers the TvShow record over the denormalised meta copy, and
    // showUrl is null for a show we hold no TvShow row for.
    { label: 'Show', value: props.entry.showTitle ?? meta.value.show_title, href: props.entry.showUrl },
    { label: 'Season', value: meta.value.season },
    { label: 'Episode', value: meta.value.episode },
    {
        label: 'Runtime',
        value: runtime.value && measure('duration', meta.value.runtime * 60, runtime.value),
        title: runtime.value && exactMeasure('duration', meta.value.runtime * 60, runtime.value),
    },
]);
</script>

<template>
    <div class="space-y-8">
        <EntryHero
            v-if="entry.backdrop"
            :backdrop="entry.backdrop"
            :logo="entry.logo"
            :poster="entry.poster"
            :title="entry.title ?? ''"
        />

        <div v-if="entry.rating" class="flex items-center gap-2">
            <Icon name="StarIcon" class="size-5 text-accent-500" />
            <Stat>{{ entry.rating }}</Stat>
            <span class="text-sm text-neutral-500">/ 10</span>
        </div>

        <div v-if="genres.length" class="flex flex-wrap gap-2">
            <Pill v-for="genre in genres" :key="genre" :label="genre" />
        </div>

        <DetailList :rows="rows" />
    </div>
</template>
