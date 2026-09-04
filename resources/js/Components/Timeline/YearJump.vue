<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Year shortcuts for the timeline's pagination bar. The feed runs to hundreds
 * of pages, so the way out of it is a date, not a page number: each year links
 * to the archive that already exists at /{year}.
 */
defineProps({
    // list<{ year: Number, href: String }>, newest first.
    years: { type: Array, default: () => [] },
    // Highlighted as where the visible page sits, when it sits in one year.
    current: { type: Number, default: null },
});
</script>

<template>
    <div v-if="years.length" class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-2">
        <span class="text-meta text-neutral-500">Jump to</span>

        <Link
            v-for="entry in years"
            :key="entry.year"
            :href="entry.href"
            class="text-meta tnum underline-offset-4 transition-colors hover:text-accent-500 hover:underline focus-visible:text-accent-500 focus-visible:underline focus-visible:outline-none"
            :class="entry.year === current ? 'font-semibold text-neutral-900' : 'text-neutral-500'"
            :aria-current="entry.year === current ? 'true' : undefined"
        >{{ entry.year }}</Link>
    </div>
</template>
