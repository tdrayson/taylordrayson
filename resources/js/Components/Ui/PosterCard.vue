<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Film01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';

const props = defineProps({
    slug: { type: String, required: true },
    title: { type: String, required: true },
    year: { type: [Number, String], default: null },
    poster: { type: String, default: null },
    // Percentage of aired episodes watched (0-100); null hides the bar entirely
    // (e.g. a show with no known episode count yet).
    progress: { type: Number, default: null },
});

const href = computed(() => `/media/tv/${props.slug}`);
// Accessible name for the whole tile since its visible title/year sit in
// separate text nodes rather than one readable phrase.
const label = computed(() => (props.year ? `${props.title}, ${props.year}` : props.title));
</script>

<template>
    <Link
        :href="href"
        data-testid="poster-card"
        :aria-label="label"
        class="group block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
    >
        <div class="relative aspect-2/3 overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 transition-shadow group-hover:shadow-card">
            <img
                v-if="poster"
                :src="poster"
                alt=""
                loading="lazy"
                class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
            >
            <div v-else class="flex size-full flex-col items-center justify-center gap-2 text-neutral-400">
                <Icon :icon="Film01Icon" class="size-7" />
                <span class="font-display text-item-title">{{ title.charAt(0) }}</span>
            </div>
            <div v-if="progress !== null" class="absolute inset-x-0 bottom-0 h-1 bg-neutral-0/40">
                <div class="h-full bg-accent-500" :style="{ width: `${progress}%` }" />
            </div>
        </div>
        <p class="mt-2 truncate text-meta font-medium text-neutral-900 transition-colors group-hover:text-accent-500">{{ title }}</p>
        <p v-if="year" class="text-caption text-neutral-500">{{ year }}</p>
    </Link>
</template>
