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

    <div v-if="subjects.length" class="mt-10 grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
        <Link v-for="subject in subjects" :key="subject.slug" :href="subject.url" class="group block">
            <SubjectImage :cover="subject.cover" :name="subject.name" :kind="kindLabel" />
            <p class="mt-2 truncate text-meta font-medium text-neutral-900 transition-colors group-hover:text-accent-500">{{ subject.name }}</p>
            <p v-if="subject.category" class="text-caption text-neutral-500">{{ subject.category }}</p>
        </Link>
    </div>

    <p v-else class="mt-10 text-meta text-neutral-500">Nothing here yet.</p>
</template>
