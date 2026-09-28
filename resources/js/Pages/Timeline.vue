<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import IntroBlock from '../Components/Timeline/IntroBlock.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import AuthorRef from '../Components/Profile/AuthorRef.vue';
import Pagination from '../Components/Ui/Pagination.vue';
import YearJump from '../Components/Timeline/YearJump.vue';
import { formatRange } from '../lib/dateFormat.js';
import { filterVisit, useFilterTransition } from '../lib/filterTransition.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    groups: { type: Array, default: () => [] },
    // { from, to } as Y-m-d, the oldest and newest day on this page.
    range: { type: Object, default: null },
    olderUrl: { type: String, default: null },
    newerUrl: { type: String, default: null },
    // list<{ year, href }>, newest first.
    years: { type: Array, default: () => [] },
    thisWeekWithEpisodes: { type: Number, default: 0 },
    // The feed preset key the page is filtered to.
    filter: { type: String, default: 'curated' },
    // list<{ value, label }>, every preset the filter offers.
    filters: { type: Array, default: () => [] },
});

// Hugeicons per FeedPresets key; a preset missing here just has no icon.
const FILTER_ICONS = {
    curated: 'SparklesIcon',
    everything: 'GridViewIcon',
    writing: 'File01Icon',
    watching: 'TvMinimalPlayIcon',
    travel: 'AirplaneTakeOff01Icon',
    health: 'WorkoutRunIcon',
    'going-out': 'Ticket01Icon',
    speaking: 'Mic01Icon',
};

// `?filter=` saves the choice in a cookie and redirects to the front of the feed.
const filterMenu = props.filters.map((preset) => ({
    label: preset.label,
    icon: FILTER_ICONS[preset.value],
    href: `/?filter=${preset.value}`,
    description: preset.value === props.filter ? 'Showing' : undefined,
    visit: filterVisit(),
}));

const filtering = useFilterTransition();

setLayoutProps({
    breadcrumb: [
        {
            label: props.filters.find((preset) => preset.value === props.filter)?.label ?? 'Everything',
            ariaLabel: 'Filter the timeline',
            menu: filterMenu,
        },
    ],
});

// The intro only belongs on the front of the feed, which is now the page with
// nothing newer than it rather than page 1.
const isFront = computed(() => props.newerUrl === null);

/** The page's span as one line, e.g. "1-22 Sep 2026". */
const rangeLabel = computed(() => (props.range ? formatRange(props.range.from, props.range.to) : ''));

// The year the page sits in, for the jump control to mark. Null when it straddles two.
const currentYear = computed(() => {
    if (!props.range) {
        return null;
    }

    const from = Number(props.range.from.slice(0, 4));

    return from === Number(props.range.to.slice(0, 4)) ? from : null;
});
</script>

<template>
    <AppHead :og="og" />

    <IntroBlock v-if="isFront" :this-week-with-episodes="thisWeekWithEpisodes" class="mb-14" />

    <div v-if="groups.length" class="h-feed flex flex-col gap-14">
        <h1 class="p-name sr-only">Taylor Drayson timeline</h1>
        <AuthorRef />
        <DateGroup
            v-for="group in groups"
            :key="group.label"
            :label="group.label"
            :date="group.date"
            :href="group.href"
            :items="group.items"
            :animate-filter="filtering"
        />
    </div>

    <p v-else class="text-sm text-neutral-500">No entries yet.</p>

    <div v-if="olderUrl || newerUrl" class="mt-14">
        <Pagination
            prev-label="Newer"
            next-label="Older"
            :prev-url="newerUrl"
            :next-url="olderUrl"
        >
            <template #label>{{ rangeLabel }}</template>
        </Pagination>

        <YearJump :years="years" :current="currentYear" />
    </div>
</template>

<style>
/* Switching filter runs a view transition over the named cards and headings:
   ones on both pages slide to their new place, the rest leave or arrive.
   Global because the pseudo-elements live on the document. */
::view-transition-group(.timeline-item) {
    animation-duration: 0.45s;
    animation-timing-function: var(--ease-out-expo);
}

::view-transition-old(.timeline-item):only-child {
    animation: timeline-item-out 0.2s ease-in both;
}

::view-transition-new(.timeline-item):only-child {
    animation: timeline-item-in 0.4s var(--ease-out-expo) 0.12s both;
}

@keyframes timeline-item-out {
    to {
        opacity: 0;
        transform: translateX(-2rem);
    }
}

@keyframes timeline-item-in {
    from {
        opacity: 0;
        transform: translateX(2rem);
    }
}

@keyframes timeline-item-fade {
    from {
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    ::view-transition-group(.timeline-item) {
        animation: none;
    }

    ::view-transition-old(.timeline-item):only-child {
        animation: timeline-item-fade 0.2s ease reverse both;
    }

    ::view-transition-new(.timeline-item):only-child {
        animation: timeline-item-fade 0.2s ease both;
    }
}
</style>
