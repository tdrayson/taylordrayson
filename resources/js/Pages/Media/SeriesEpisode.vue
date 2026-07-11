<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import { StarIcon } from '@hugeicons-pro/core-stroke-rounded';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';
import { dateLong, time } from '../../lib/format.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // { slug, title }
    series: { type: Object, required: true },
    season: { type: Number, required: true },
    episode: { type: Number, required: true },
    // Every watch instance of this episode, oldest first: [{ id, occurredAt, title, rating }]
    watches: { type: Array, default: () => [] },
});

// SxxExx code, e.g. season 1 episode 3 -> S01E03.
const code = computed(() => `S${String(props.season).padStart(2, '0')}E${String(props.episode).padStart(2, '0')}`);
// Every watch instance shares the same episode title, so the first is enough.
const title = computed(() => props.watches[0]?.title ?? code.value);
const watchCountLabel = computed(() => `${props.watches.length} ${props.watches.length === 1 ? 'watch' : 'watches'}`);

const accentStyle = { color: 'var(--color-media)' };

setLayoutProps({
    breadcrumb: [
        { label: 'Media', href: '/media' },
        { label: 'TV', href: '/media/tv' },
        { label: props.series.title, href: `/media/tv/${props.series.slug}` },
        { label: `Season ${props.season}`, href: `/media/tv/${props.series.slug}/season-${props.season}` },
        { label: code.value },
    ],
});
</script>

<template>
    <AppHead :og="{ title: `${title}, ${code}`, heading: title, eyebrow: `${series.title}, ${code}`, accent: 'media' }" />

    <header>
        <span class="text-eyebrow uppercase" :style="accentStyle">{{ series.title }}, {{ code }}</span>
        <h1 class="mt-1 font-display text-display">{{ title }}</h1>
        <p class="mt-2 text-meta text-neutral-500">{{ watchCountLabel }}</p>
    </header>

    <ul v-if="watches.length" class="mt-10 divide-y divide-neutral-50 border-y border-neutral-50">
        <li v-for="watch in watches" :key="watch.id" class="flex items-center justify-between gap-4 py-3">
            <span class="text-meta text-neutral-900">{{ dateLong(watch.occurredAt) }}</span>
            <span class="flex shrink-0 items-center gap-3">
                <span v-if="watch.rating" class="flex items-center gap-1 text-caption text-neutral-500">
                    <Icon :icon="StarIcon" class="size-3.5" />
                    {{ watch.rating }}
                </span>
                <time :datetime="watch.occurredAt" class="text-caption text-neutral-500 tnum">{{ time(watch.occurredAt) }}</time>
            </span>
        </li>
    </ul>
</template>
