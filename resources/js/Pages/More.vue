<script setup>
import { setLayoutProps, Link } from '@inertiajs/vue3';
import { RssIcon, PaintBoardIcon, CalendarFavorite01Icon, UserGroupIcon } from '@hugeicons-pro/core-stroke-rounded';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Icon from '../Components/Ui/Icon.vue';
import SectionHead from '../Components/Ui/SectionHead.vue';
import Heading from '../Components/Ui/Heading.vue';
import { entryTypes, timelineTypes } from '../entryTypes.js';
import { number } from '../lib/format.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

defineProps({
    // [{ type, label, href, count }] straight from the TypeRegistry.
    tracked: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    og: { type: Object, default: () => ({}) },
});

setLayoutProps({
    breadcrumb: [{ label: 'More' }],
});

// "1 project" but "16 articles". Nouns come from each dataset via types.generated.js;
// anything unknown counts as entries rather than breaking the row.
function countLabel(item) {
    const type = timelineTypes[item.type];
    const singular = type?.noun ?? 'entry';
    const plural = type?.nounPlural ?? 'entries';

    return `${number(item.count)} ${item.count === 1 ? singular : plural}`;
}

const site = [
    { label: 'Life', description: 'The people, pets, spots and things that show up across this site', href: '/life', icon: UserGroupIcon },
    { label: 'On this day', description: 'Everything I\'ve logged on today\'s date, across every year', href: '/on-this-day', icon: CalendarFavorite01Icon },
    { label: 'Feeds', description: 'RSS and JSON, filterable by type', href: '/feeds', icon: RssIcon },
    { label: 'Design system', description: 'The components this site is built from', href: '/design-system', icon: PaintBoardIcon },
];
</script>

<template>
    <AppHead title="More" :og="og" />

    <header>
        <Heading as="h1" size="display" class="max-w-2xl">More</Heading>
        <p class="mt-3 max-w-prose text-lg text-neutral-700">
            Everything on this site that doesn't live in the sidebar: the full index of what I track, and the pages around it.
        </p>
    </header>

    <section>
        <SectionHead title="What I track" :meta="`${tracked.length} types, ${number(total)} entries`" />
        <ul class="grid gap-x-24 sm:grid-cols-2">
            <li v-for="item in tracked" :key="item.type">
                <Link
                    :href="item.href"
                    class="group flex items-center gap-3 rounded-md py-2.5 transition-colors"
                    :style="{ '--type-color': `var(--color-${entryTypes[item.type]?.accent ?? 'note'})` }"
                >
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-neutral-25 text-(--type-color)">
                        <Icon :icon="entryTypes[item.type]?.icon" class="size-5" />
                    </span>
                    <span class="font-medium text-neutral-900 underline-offset-4 group-hover:underline group-focus-visible:underline">{{ item.label }}</span>
                    <span class="ml-auto text-sm text-neutral-500 tabular-nums">{{ countLabel(item) }}</span>
                </Link>
            </li>
        </ul>
    </section>

    <section>
        <SectionHead title="Site" />
        <ul class="grid gap-x-24 sm:grid-cols-2">
            <li v-for="item in site" :key="item.href">
                <Link
                    :href="item.href"
                    class="group flex items-center gap-3 rounded-md py-2.5 transition-colors"
                >
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-neutral-25 text-neutral-500">
                        <Icon :icon="item.icon" class="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block font-medium text-neutral-900 underline-offset-4 group-hover:underline group-focus-visible:underline">{{ item.label }}</span>
                        <span class="block truncate text-sm text-neutral-500">{{ item.description }}</span>
                    </span>
                </Link>
            </li>
        </ul>
    </section>
</template>
