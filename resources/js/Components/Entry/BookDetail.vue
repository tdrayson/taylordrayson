<script setup>
import { computed } from 'vue';
import DetailList from '../Ui/DetailList.vue';
import Icon from '../Ui/Icon.vue';
import MediaHero from '../Ui/MediaHero.vue';
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

        <DetailList :rows="rows" />
    </div>
</template>
