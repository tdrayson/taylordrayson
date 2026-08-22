<script setup>
import { setLayoutProps, Link } from '@inertiajs/vue3';
import { RssIcon, PaintBoardIcon, CalendarFavorite01Icon } from '@hugeicons-pro/core-stroke-rounded';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Icon from '../Components/Ui/Icon.vue';
import SectionHead from '../Components/Ui/SectionHead.vue';
import { entryTypes } from '../entryTypes.js';
import { number } from '../lib/format.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

defineProps({
    // [{ type, label, href, count }] straight from the TypeRegistry.
    tracked: { type: Array, default: () => [] },
    og: { type: Object, default: () => ({}) },
});

setLayoutProps({
    breadcrumb: [{ label: 'More' }],
});

// What a count represents, per type: [singular, plural].
const NOUNS = {
    activity: ['activity', 'activities'],
    sleep: ['night', 'nights'],
    calorie: ['day', 'days'],
    media: ['logged', 'logged'],
    event: ['event', 'events'],
    appearance: ['appearance', 'appearances'],
    podcast: ['episode', 'episodes'],
    flight: ['flight', 'flights'],
    checkin: ['check-in', 'check-ins'],
    fuel: ['fill-up', 'fill-ups'],
    project: ['project', 'projects'],
    article: ['article', 'articles'],
    note: ['note', 'notes'],
};

// "1 project" but "16 articles".
function countLabel(item) {
    const [singular, plural] = NOUNS[item.type] ?? ['entry', 'entries'];

    return `${number(item.count)} ${item.count === 1 ? singular : plural}`;
}

const site = [
    { label: 'On this day', description: 'Everything I\'ve logged on today\'s date, across every year', href: '/on-this-day', icon: CalendarFavorite01Icon },
    { label: 'Feeds', description: 'RSS and JSON, filterable by type', href: '/feeds', icon: RssIcon },
    { label: 'Design system', description: 'The components this site is built from', href: '/design-system', icon: PaintBoardIcon },
];
</script>

<template>
    <AppHead title="More" :og="og" />

    <header>
        <h1 class="max-w-2xl font-display text-display">More</h1>
        <p class="mt-3 max-w-prose text-body text-lg text-neutral-700">
            Everything on this site that doesn't live in the sidebar: the full index of what I track, and the pages around it.
        </p>
    </header>

    <section>
        <SectionHead title="What I track" :meta="`${tracked.length} types`" />
        <ul class="grid gap-x-8 sm:grid-cols-2">
            <li v-for="item in tracked" :key="item.type">
                <Link
                    :href="item.href"
                    class="group flex items-center gap-3 rounded-md py-2.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    :style="{ '--type-color': `var(--color-${entryTypes[item.type]?.accent ?? 'note'})` }"
                >
                    <span class="type-color flex size-9 shrink-0 items-center justify-center rounded-full bg-neutral-25">
                        <Icon :icon="entryTypes[item.type]?.icon" class="size-5" />
                    </span>
                    <span class="font-medium text-neutral-900 underline-offset-4 group-hover:underline group-focus-visible:underline">{{ item.label }}</span>
                    <span class="ml-auto text-meta text-neutral-500 tnum">{{ countLabel(item) }}</span>
                </Link>
            </li>
        </ul>
    </section>

    <section>
        <SectionHead title="Site" />
        <ul class="grid gap-x-8 sm:grid-cols-2">
            <li v-for="item in site" :key="item.href">
                <Link
                    :href="item.href"
                    class="group flex items-center gap-3 rounded-md py-2.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                >
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-neutral-25 text-neutral-500">
                        <Icon :icon="item.icon" class="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block font-medium text-neutral-900 underline-offset-4 group-hover:underline group-focus-visible:underline">{{ item.label }}</span>
                        <span class="block truncate text-meta text-neutral-500">{{ item.description }}</span>
                    </span>
                </Link>
            </li>
        </ul>
    </section>
</template>

<style scoped>
.type-color {
    color: var(--type-color);
}
</style>
