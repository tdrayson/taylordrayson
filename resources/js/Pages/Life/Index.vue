<script setup>
import { setLayoutProps, Link } from '@inertiajs/vue3';
import { UserIcon, PawPrintIcon, Location01Icon, CubeIcon } from '@hugeicons-pro/core-stroke-rounded';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';
import { number } from '../../lib/format.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

defineProps({
    // [{label, segment, count, recent: [{name, url, cover}]}], one per kind.
    kinds: { type: Array, default: () => [] },
    og: { type: Object, default: () => ({}) },
});

setLayoutProps({
    breadcrumb: [{ label: 'Life' }],
});

const ICONS = {
    people: UserIcon,
    pets: PawPrintIcon,
    spots: Location01Icon,
    things: CubeIcon,
};

// Static copy rather than a column on the subject table: these describe the
// four kinds, which are fixed in the enum, not anything a page can edit.
const BLURBS = {
    people: 'Family, friends and the people who turn up most often, with every entry and photograph they are in.',
    pets: 'The dogs and cats, mine and other people\'s, with rather more photographs than is strictly reasonable.',
    spots: 'The cafes, trails, gyms and venues I keep going back to, each with a map and everything logged there.',
    things: 'Bikes, cameras, computers and whatever else has been used enough to leave a trail behind it.',
};
</script>

<template>
    <AppHead :og="og" />

    <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:gap-10">
        <span class="inline-flex size-32 shrink-0 items-end justify-center overflow-hidden rounded-full bg-accent-100 sm:size-40">
            <img src="/taylor-cutout.png" alt="" class="h-full w-auto object-contain object-bottom">
        </span>

        <div>
            <h1 class="max-w-2xl font-display text-display">Life</h1>
            <p class="mt-3 max-w-lg text-body text-neutral-500">
                The people, pets, spots and things that show up across this site: pulled out of the timeline
                and given pages of their own, wherever a photograph or an entry is tagged with them.
            </p>
        </div>
    </div>

    <div class="mt-14 flex flex-col divide-y divide-neutral-50 border-t border-neutral-50">
        <div v-for="kind in kinds" :key="kind.segment" class="flex items-start gap-6 py-10">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-accent-50 text-accent-500">
                <Icon :icon="ICONS[kind.segment]" class="size-7" />
            </span>

            <div class="min-w-0">
                <h2 class="font-display text-name">
                    <Link :href="`/life/${kind.segment}`" class="text-neutral-900 underline decoration-neutral-100 underline-offset-4 transition-colors hover:text-accent-500 focus-visible:text-accent-500">{{ kind.label }}</Link>
                </h2>

                <p class="mt-2 max-w-xl text-lead text-neutral-500">{{ BLURBS[kind.segment] }}</p>

                <Link :href="`/life/${kind.segment}`" class="mt-3 inline-block text-meta text-neutral-400 transition-colors hover:text-accent-500 focus-visible:text-accent-500">
                    <span class="tnum">{{ number(kind.count) }}</span> {{ kind.label.toLowerCase() }}
                </Link>
            </div>
        </div>
    </div>
</template>
