<script setup>
import { computed } from 'vue';
import { StarIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import DetailList from '../Ui/DetailList.vue';
import Pill from '../Ui/Pill.vue';
import { number, titleCase } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const meta = computed(() => props.entry.meta || {});

const genres = computed(() => (Array.isArray(meta.value.genres) ? meta.value.genres : []));

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
        <div v-if="entry.rating" class="flex items-center gap-2">
            <Icon :icon="StarIcon" class="size-5 text-accent" />
            <span class="font-display text-stat tnum">{{ entry.rating }}</span>
            <span class="text-meta text-ink-3">/ 10</span>
        </div>

        <div v-if="genres.length" class="flex flex-wrap gap-2">
            <Pill v-for="genre in genres" :key="genre" :label="genre" />
        </div>

        <DetailList :rows="rows" />
    </div>
</template>
