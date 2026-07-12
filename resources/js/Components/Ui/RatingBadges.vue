<script setup>
import { computed } from 'vue';
import Pill from './Pill.vue';

const props = defineProps({
    // Shape from meta.ratings (EnrichMedia job / OMDB): { imdb, imdb_votes,
    // rotten_tomatoes, metacritic, certification, awards, box_office }. Any
    // subset may be present; absent sources are simply omitted.
    ratings: { type: Object, default: null },
});

// Build one pill per available source, in a fixed critic-then-certification
// order, so the row never re-orders itself as different titles carry
// different subsets of ratings.
const badges = computed(() => {
    if (!props.ratings) {
        return [];
    }

    const list = [];

    if (props.ratings.imdb) {
        list.push({ key: 'imdb', label: `IMDb ${props.ratings.imdb}` });
    }

    if (props.ratings.rotten_tomatoes) {
        list.push({ key: 'rotten_tomatoes', label: `RT ${props.ratings.rotten_tomatoes}` });
    }

    if (props.ratings.metacritic) {
        list.push({ key: 'metacritic', label: `Metacritic ${props.ratings.metacritic}` });
    }

    if (props.ratings.certification) {
        list.push({ key: 'certification', label: props.ratings.certification });
    }

    return list;
});
</script>

<template>
    <div v-if="badges.length" class="flex flex-wrap gap-2">
        <Pill v-for="badge in badges" :key="badge.key" data-testid="rating-badge" :label="badge.label" variant="outline" />
    </div>
</template>
