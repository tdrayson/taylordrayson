<script setup>
import { setLayoutProps, Link } from '@inertiajs/vue3';
import { UserIcon, FootprintsIcon, Location01Icon, CubeIcon } from '@hugeicons-pro/core-stroke-rounded';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';
import ExternalLink from '../../Components/Ui/ExternalLink.vue';
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
    pets: FootprintsIcon,
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

    <header>
        <h1 class="max-w-2xl font-display text-display">Life</h1>
    </header>

    <div class="mt-8 grid gap-8 sm:grid-cols-5 sm:items-start sm:gap-12">
        <div class="sm:col-span-3">
            <p class="text-body text-lg text-neutral-700">
                The people, pets, spots and things that show up across this site.
            </p>

            <div class="mt-4 flex flex-col gap-4 text-body text-neutral-500">
                <p>
                    Everything logged here happened somewhere, usually with someone, and often using something.
                    This is where those get pages of their own, so the entries stop being a list of days and start
                    joining up.
                </p>
                <p>
                    Photographs are tagged with the
                    <Link href="/life/people" class="font-medium text-neutral-900 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500">people</Link>
                    and
                    <Link href="/life/pets" class="font-medium text-neutral-900 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500">pets</Link>
                    in them, so you can watch the same faces turn up across years of walks and coffees.
                </p>
                <p>
                    You can also wander the
                    <Link href="/life/spots" class="font-medium text-neutral-900 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500">spots</Link>
                    I keep coming back to, and the
                    <Link href="/life/things" class="font-medium text-neutral-900 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500">things</Link>
                    that carried me there or took the picture.
                </p>
                <p>
                    Borrowed, with thanks, from
                    <ExternalLink href="https://chrisglass.com/characters/" label="Chris Glass&rsquo; characters" />
                    and
                    <ExternalLink href="https://www.trovster.com/life" label="Trevor Morris&rsquo; life" />
                    pages.
                </p>
            </div>
        </div>

        <img
            src="/headshot-taylor.jpg"
            alt="Taylor Drayson"
            class="w-full rounded-lg border border-neutral-50 sm:col-span-2"
        >
    </div>

    <div class="mt-12 grid gap-8 border-t border-neutral-50 pt-8 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="kind in kinds" :key="kind.segment">
            <h2 class="font-display text-item-title">
                <Link :href="`/life/${kind.segment}`" class="text-neutral-900 underline decoration-neutral-100 underline-offset-4 transition-colors hover:text-accent-500 focus-visible:text-accent-500">{{ kind.label }}</Link>
            </h2>

            <p class="mt-2 text-meta text-neutral-500">{{ BLURBS[kind.segment] }}</p>

            <p class="mt-3 flex items-center gap-2 text-meta text-neutral-500">
                <Icon :icon="ICONS[kind.segment]" class="size-5 text-accent-500" />
                <span class="tnum">{{ number(kind.count) }}</span>
            </p>
        </div>
    </div>
</template>
