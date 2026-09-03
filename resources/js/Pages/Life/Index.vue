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

// The tile's backdrop is the kind's own recent covers. One fills the frame;
// several tile into a mosaic, so the page opens with faces rather than counts.
function coversOf(kind) {
    return kind.recent.filter((subject) => subject.cover).slice(0, 4);
}

function mosaicClass(count) {
    return count > 1 ? 'grid-cols-2' : 'grid-cols-1';
}

// Three covers would leave a hole in a 2x2, so the first one spans the row.
function tileClass(index, count) {
    return count === 3 && index === 0 ? 'col-span-2' : '';
}
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="max-w-2xl font-display text-display">Life</h1>
        <p class="mt-3 max-w-prose text-body text-lg text-neutral-700">
            The people, pets, spots and things that show up across this site.
        </p>
    </header>

    <div class="mt-10 grid gap-4 sm:grid-cols-2">
        <Link
            v-for="kind in kinds"
            :key="kind.segment"
            :href="`/life/${kind.segment}`"
            class="group relative block h-64 overflow-hidden rounded-lg bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 sm:h-72"
        >
            <div
                v-if="coversOf(kind).length"
                class="grid size-full gap-0.5"
                :class="mosaicClass(coversOf(kind).length)"
            >
                <span
                    v-for="(subject, index) in coversOf(kind)"
                    :key="subject.url"
                    class="overflow-hidden bg-neutral-25"
                    :class="tileClass(index, coversOf(kind).length)"
                >
                    <img
                        :src="subject.cover.src"
                        :srcset="subject.cover.srcset || undefined"
                        alt=""
                        loading="lazy"
                        class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                    >
                </span>
            </div>

            <span v-else class="flex size-full items-center justify-center text-neutral-300">
                <Icon :icon="ICONS[kind.segment]" class="size-12" />
            </span>

            <!-- Fixed black, not the neutral ramp: an intentional dark surface in both themes. -->
            <span class="pointer-events-none absolute inset-x-0 bottom-0 flex items-end justify-between gap-4 bg-gradient-to-t from-black/90 via-black/45 to-transparent p-5 pt-24">
                <span class="min-w-0">
                    <span class="flex items-center gap-2 text-white">
                        <Icon :icon="ICONS[kind.segment]" class="size-5" />
                        <span class="font-display text-item-title">{{ kind.label }}</span>
                    </span>
                    <span class="mt-1 block text-meta text-white/75 tnum">{{ number(kind.count) }}</span>
                </span>

                <span class="shrink-0 text-white/75 transition-transform duration-300 group-hover:translate-x-1">
                    <Icon name="ArrowRight01Icon" class="size-5" />
                </span>
            </span>
        </Link>
    </div>
</template>
