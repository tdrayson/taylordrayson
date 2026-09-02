<script setup>
import { setLayoutProps, Link } from '@inertiajs/vue3';
import { UserIcon, FootprintsIcon, Location01Icon, CubeIcon } from '@hugeicons-pro/core-stroke-rounded';
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
    pets: FootprintsIcon,
    spots: Location01Icon,
    things: CubeIcon,
};
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="max-w-2xl font-display text-display">Life</h1>
        <p class="mt-3 max-w-prose text-body text-lg text-neutral-700">
            The people, pets, spots and things that show up across this site.
        </p>
    </header>

    <div class="mt-10 grid gap-6 sm:grid-cols-2">
        <Link
            v-for="kind in kinds"
            :key="kind.segment"
            :href="`/life/${kind.segment}`"
            class="group rounded-lg border border-neutral-50 p-5 transition-colors hover:border-accent-200 focus-visible:border-accent-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
        >
            <div class="flex items-center gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-neutral-25 text-neutral-500">
                    <Icon :icon="ICONS[kind.segment]" class="size-5" />
                </span>
                <span class="min-w-0">
                    <span class="block font-display text-item-title text-neutral-900 underline-offset-4 group-hover:underline group-focus-visible:underline">{{ kind.label }}</span>
                    <span class="block text-meta text-neutral-500 tnum">{{ number(kind.count) }}</span>
                </span>
            </div>

            <div v-if="kind.recent.length" class="mt-4 flex -space-x-2">
                <span
                    v-for="recent in kind.recent"
                    :key="recent.url"
                    class="size-9 overflow-hidden rounded-full border-2 border-neutral-0 bg-neutral-25"
                >
                    <img v-if="recent.cover" :src="recent.cover.src" :alt="recent.cover.alt || recent.name" class="size-full object-cover">
                </span>
            </div>
        </Link>
    </div>
</template>
