<script setup>
import { Link } from '@inertiajs/vue3';
import { setLayoutProps } from '../../composables/useLayout.js';
import { ArrowRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';

defineOptions({ layout: AppLayout });

defineProps({
    og: { type: Object, default: () => ({}) },
    // [{ slug, type, title, description, accent }]
    stories: { type: Array, default: () => [] },
});

setLayoutProps({ breadcrumb: [{ label: 'Data stories' }] });
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="max-w-2xl font-display text-display text-neutral-900">Data stories</h1>
        <p class="mt-4 max-w-xl text-body text-neutral-600">
            In-depth looks at the data I keep on myself: the long reads behind the numbers, refreshed every so often as
            the data grows.
        </p>
    </header>

    <ul class="mt-10 grid gap-4 sm:grid-cols-2">
        <li v-for="story in stories" :key="story.slug">
            <Link
                :href="`/stories/${story.slug}`"
                :style="{ '--accent': `#${story.accent}` }"
                class="story-card group relative flex h-full flex-col overflow-hidden rounded-xl border border-neutral-50 p-6 transition-colors focus-visible:outline-none"
            >
                <!-- Soft accent blob glowing from the bottom-right corner, like the OG card. -->
                <span class="pointer-events-none absolute -bottom-12 -right-12 size-44 rounded-full opacity-25 blur-2xl" :style="{ backgroundColor: `#${story.accent}` }" />
                <h2 class="relative font-display text-item-title text-neutral-900">{{ story.title }}</h2>
                <p class="relative mt-2 flex-1 text-meta text-neutral-600">{{ story.description }}</p>
                <span class="relative mt-5 inline-flex items-center gap-1.5 text-caption font-medium text-neutral-900">
                    Read the story
                    <Icon :icon="ArrowRight01Icon" class="size-4 transition-transform group-hover:translate-x-0.5 group-focus-visible:translate-x-0.5" />
                </span>
            </Link>
        </li>
    </ul>
</template>

<style scoped>
/* The accent (set inline per card via --accent) tints the border and a faint
   wash on hover/focus, so each card lights up in its own data-type colour. */
.story-card:hover,
.story-card:focus-visible {
    border-color: var(--accent);
    background-color: color-mix(in srgb, var(--accent) 5%, transparent);
}
</style>
