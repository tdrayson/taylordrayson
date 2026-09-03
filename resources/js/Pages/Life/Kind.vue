<script setup>
import { setLayoutProps, Link } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
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
    <div v-if="subjects.length" class="mt-10 columns-2 gap-3 sm:columns-3 lg:columns-4">
        <Link
            v-for="subject in subjects"
            :key="subject.slug"
            :href="subject.url"
            class="group relative mb-3 block break-inside-avoid overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
        >
            <img
                v-if="subject.cover"
                :src="subject.cover.src"
                :srcset="subject.cover.srcset || undefined"
                sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw"
                :alt="subject.name"
                loading="lazy"
                class="w-full"
            >
            <SubjectImage v-else :cover="null" :name="subject.name" :kind="kindLabel" class="border-0 bg-transparent" />

            <!-- Fixed black, not the neutral ramp: an intentional dark surface in both themes. -->
            <span class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent p-3 pt-8 opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                <span class="block truncate text-meta font-medium text-white">{{ subject.name }}</span>
            </span>
        </Link>
    </div>

    <p v-else class="mt-10 text-meta text-neutral-500">Nothing here yet.</p>
</template>
