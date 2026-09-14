<script setup>
import { computed } from 'vue';
import DetailList from '../Ui/DetailList.vue';
import Icon from '../Ui/Icon.vue';
import EntryHero from '../Ui/EntryHero.vue';
import { titleCase } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const meta = computed(() => props.entry.meta || {});

const rows = computed(() => [
    { label: 'Type', value: titleCase(props.entry.type) },
    { label: 'Author', value: meta.value.author },
    { label: 'ISBN', value: meta.value.isbn },
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
            <span class="font-display text-stat tnum">{{ entry.rating }}</span>
            <span class="text-meta text-neutral-500">/ 10</span>
        </div>

        <p v-if="entry.overview" v-twemoji class="max-w-prose whitespace-pre-line text-body text-neutral-700">{{ entry.overview }}</p>

        <DetailList :rows="rows" />
    </div>
</template>
