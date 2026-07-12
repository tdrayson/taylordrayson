<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { StarIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import { time } from '../../lib/format.js';

const props = defineProps({
    season: { type: [Number, String], required: true },
    episode: { type: [Number, String], required: true },
    title: { type: String, required: true },
    occurredAt: { type: String, required: true },
    rating: { type: [Number, String], default: null },
    // Standard entry URL (`Media::url()`), so an episode row goes to its
    // normal date-anchored entry page like every other timeline entry.
    url: { type: String, required: true },
});

// SxxExx code, e.g. season 1 episode 3 -> S01E03.
const code = computed(() => `S${String(props.season).padStart(2, '0')}E${String(props.episode).padStart(2, '0')}`);
const watchTime = computed(() => time(props.occurredAt));
</script>

<template>
    <Link
        :href="url"
        data-testid="episode-row"
        class="group relative flex items-center justify-between gap-4 rounded-md px-3 py-2.5 transition-colors after:absolute after:inset-x-0 after:bottom-0 after:h-px after:bg-neutral-100 last:after:hidden hover:bg-neutral-25 focus-visible:bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
    >
        <span class="flex min-w-0 items-baseline gap-3">
            <span class="shrink-0 text-caption font-semibold text-neutral-500 tnum">{{ code }}</span>
            <span class="truncate text-meta font-medium text-neutral-900 transition-colors group-hover:text-accent-500">{{ title }}</span>
        </span>
        <span class="flex shrink-0 items-center gap-3">
            <span v-if="rating" class="flex items-center gap-1 text-caption text-neutral-500">
                <Icon :icon="StarIcon" class="size-3.5" />
                {{ rating }}
            </span>
            <time v-if="watchTime" :datetime="occurredAt" class="text-caption text-neutral-500 tnum">{{ watchTime }}</time>
        </span>
    </Link>
</template>
