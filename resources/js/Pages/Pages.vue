<script setup>
import { setLayoutProps, Link } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

defineProps({
    // [{ title, href }] for every standalone page.
    pages: { type: Array, default: () => [] },
    og: { type: Object, default: () => ({}) },
});

setLayoutProps({
    breadcrumb: [{ label: 'Pages' }],
});
</script>

<template>
    <AppHead title="Pages" :og="og" />

    <header>
        <h1 class="max-w-2xl font-display text-display">Pages</h1>
        <p class="mt-3 max-w-prose text-body text-lg text-neutral-700">
            Standalone pages that live outside the timeline.
        </p>
    </header>

    <ul v-if="pages.length" class="mt-10 list-disc space-y-2 pl-5 text-body">
        <li v-for="page in pages" :key="page.href">
            <Link :href="page.href" class="font-medium text-neutral-900 underline decoration-neutral-300 underline-offset-4 transition-colors hover:text-accent-500 hover:decoration-accent-500 focus-visible:text-accent-500 focus-visible:outline-none">{{ page.title }}</Link>
        </li>
    </ul>

    <p v-else class="mt-10 text-meta text-neutral-500">Nothing here yet.</p>
</template>
