<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * The twelve months of a year archive, as links to /{year}/{month}.
 *
 * Sits with the year's header rather than in the pagination bar, so it stays
 * reachable on every page of the feed: the heatmap only renders on page one,
 * which otherwise leaves later pages with no way out but Next.
 *
 * A month with nothing logged is rendered as plain text, not a dead link, so
 * a sparse year reads as sparse instead of offering twelve empty pages.
 */
defineProps({
    year: { type: Number, required: true },
    // list<{ month: Number, label: String, href: String, total: Number }>,
    // January first, every month present even at zero.
    months: { type: Array, default: () => [] },
    // Highlighted when the page is showing days inside one month.
    current: { type: Number, default: null },
});
</script>

<template>
    <nav v-if="months.length" :aria-label="`Months of ${year}`" class="flex flex-wrap gap-x-4 gap-y-2">
        <template v-for="entry in months" :key="entry.month">
            <Link
                v-if="entry.total > 0"
                :href="entry.href"
                :title="`${entry.total.toLocaleString()} entries`"
                class="text-meta underline-offset-4 transition-colors hover:text-accent-500 hover:underline focus-visible:text-accent-500 focus-visible:underline focus-visible:outline-none"
                :class="entry.month === current ? 'font-semibold text-neutral-900' : 'text-neutral-500'"
                :aria-current="entry.month === current ? 'true' : undefined"
            >{{ entry.label }}</Link>

            <span v-else class="text-meta text-neutral-500/40">{{ entry.label }}</span>
        </template>
    </nav>
</template>
