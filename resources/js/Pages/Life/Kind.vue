<script setup>
import { setLayoutProps, Link } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { computed } from 'vue';
import SubjectImage from '../../Components/Subjects/SubjectImage.vue';
import { cn } from '../../lib/cn.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    kind: { type: String, required: true },
    kindLabel: { type: String, required: true },
    segment: { type: String, required: true },
    categories: { type: Array, default: () => [] },
    category: { type: String, default: null },
    hasCategories: { type: Boolean, default: false },
    subjects: { type: Array, default: () => [] },
    og: { type: Object, default: () => ({}) },
});

setLayoutProps({
    breadcrumb: [{ label: 'Life', href: '/life' }, { label: props.kind }],
});

// Tile size follows how much of the site a subject occupies, in three bands
// off the busiest one rather than off a fixed number, so a kind with only a
// handful of subjects still gets a mix rather than a wall of small squares.
//
// The jitter is seeded from the slug, so a subject keeps its size between
// visits: a grid that reshuffles on every load reads as broken, not playful.
//
// Spans start at 3 against a fine 12-column track, not at 1 against a coarse
// one: the smallest tile clears 180px and the steps between bands are a third
// larger each time rather than double and treble.
const SPANS = ['col-span-3 row-span-3', 'col-span-4 row-span-4', 'col-span-5 row-span-5'];

function seed(slug) {
    let hash = 0;

    for (let index = 0; index < slug.length; index++) {
        hash = (hash * 31 + slug.charCodeAt(index)) % 1000;
    }

    return hash / 1000;
}

const sized = computed(() => {
    const busiest = Math.max(1, ...props.subjects.map((subject) => subject.weight ?? 0));

    return props.subjects.map((subject) => {
        const share = (subject.weight ?? 0) / busiest;
        // Nudge by up to a third of a band either way, so the grid reads as a
        // collage rather than a ranking.
        const nudged = share + (seed(subject.slug) - 0.5) * 0.3;
        const band = nudged > 0.62 ? 2 : nudged > 0.28 ? 1 : 0;

        return { ...subject, span: SPANS[band] };
    });
});

function facetClasses(active) {
    return cn(
        'rounded-full px-3 py-1.5 text-meta font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
        active ? 'bg-accent-500 text-neutral-0' : 'bg-neutral-25 text-neutral-700 hover:bg-accent-50 hover:text-accent-700',
    );
}
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="font-display text-display">{{ kind }}</h1>
    </header>

    <div v-if="hasCategories" class="mt-6 flex flex-wrap gap-2">
        <Link :href="`/life/${segment}`" :class="facetClasses(category === null)">All</Link>
        <Link
            v-for="option in categories"
            :key="option.value"
            :href="`/life/${segment}?category=${option.value}`"
            :class="facetClasses(category === option.value)"
        >
            {{ option.label }}
        </Link>
    </div>

    <!-- A collage, not a table: each cover keeps its own shape and the name
         only surfaces on hover, so the page reads as faces rather than rows.
         CSS columns rather than PhotoGrid's row spans, since nothing here
         needs the covers measured and the order carries no meaning. -->
    <!-- Squares at three sizes, packed dense so the bigger tiles leave no
         holes. Uniform squares turned the page into a contact sheet; sizing
         them by how much of the site a subject occupies gives it a shape. -->
    <div v-if="sized.length" class="mt-10 grid auto-rows-fr grid-cols-6 gap-3 sm:grid-cols-9 lg:grid-cols-12" style="grid-auto-flow: dense">
        <Link
            v-for="subject in sized"
            :key="subject.slug"
            :href="subject.url"
            class="group relative block aspect-square overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            :class="subject.span"
        >
            <img
                v-if="subject.cover"
                :src="subject.cover.src"
                :srcset="subject.cover.srcset || undefined"
                sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 75vw"
                :alt="subject.name"
                loading="lazy"
                class="size-full object-cover"
            >
            <SubjectImage v-else :cover="null" :name="subject.name" :kind="kindLabel" class="size-full border-0 bg-transparent" />

            <!-- Fixed black, not the neutral ramp: an intentional dark surface in both themes. -->
            <span class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent p-3 pt-8 opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                <span class="block truncate text-meta font-medium text-white">{{ subject.name }}</span>
            </span>
        </Link>
    </div>

    <p v-else class="mt-10 text-meta text-neutral-500">Nothing here yet.</p>
</template>
