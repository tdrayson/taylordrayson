<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';
import DetailList from '../Ui/DetailList.vue';
import Pill from '../Ui/Pill.vue';
import MediaHero from '../Ui/MediaHero.vue';
import { number, titleCase } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const meta = computed(() => props.entry.meta || {});

// TMDB enrichment stores genres under meta.tmdb.genres; fall back to a
// top-level meta.genres for any legacy/other source.
const genres = computed(() => {
    const source = meta.value.tmdb?.genres ?? meta.value.genres;
    return Array.isArray(source) ? source : [];
});

const rows = computed(() => [
    { label: 'Type', value: titleCase(props.entry.type) },
    { label: 'Year', value: meta.value.year },
    { label: 'Show', value: meta.value.show_title },
    { label: 'Season', value: meta.value.season },
    { label: 'Episode', value: meta.value.episode },
    { label: 'Runtime', value: meta.value.runtime ? `${number(meta.value.runtime)} min` : null },
    { label: 'Author', value: meta.value.author },
    { label: 'ISBN', value: meta.value.isbn },
]);
</script>

<template>
    <div class="space-y-8">
        <MediaHero
            v-if="entry.backdrop"
            :backdrop="entry.backdrop"
            :logo="entry.logo"
            :poster="entry.poster"
            :title="entry.title ?? ''"
        />

        <div v-if="entry.rating" class="flex items-center gap-2">
            <Icon name="StarIcon" class="size-5 text-accent-500" />
            <span class="font-display text-stat tnum">{{ entry.rating }}</span>
            <span class="text-meta text-neutral-500">/ 10</span>
        </div>

        <div v-if="genres.length" class="flex flex-wrap gap-2">
            <Pill v-for="genre in genres" :key="genre" :label="genre" />
        </div>

        <DetailList :rows="rows" />
    </div>
</template>
