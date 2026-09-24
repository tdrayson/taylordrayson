<script setup>
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Ui/Icon.vue';
import Heading from '../../Components/Ui/Heading.vue';

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
        <Heading as="h1" size="display" class="max-w-2xl text-neutral-900">Data stories</Heading>
        <p class="mt-4 max-w-xl text-base text-neutral-600">
            In-depth looks at the data I keep on myself: the long reads behind the numbers, refreshed every so often as
            the data grows.
        </p>
    </header>

    <ul class="mt-10 grid gap-4 sm:grid-cols-2">
        <li v-for="story in stories" :key="story.slug">
            <Link
                :href="`/stories/${story.slug}`"
                :style="{ '--accent': `#${story.accent}` }"
                class="group relative flex h-full flex-col overflow-hidden rounded-xl border border-neutral-50 p-6 transition-colors hover:border-(--accent) hover:bg-(--accent)/5 focus-visible:bg-(--accent)/5"
            >
                <!-- Soft accent blob glowing from the bottom-right corner, like the OG card. -->
                <span class="pointer-events-none absolute -bottom-12 -right-12 size-44 rounded-full opacity-25 blur-2xl" :style="{ backgroundColor: `#${story.accent}` }" />
                <Heading size="title" class="relative text-neutral-900">{{ story.title }}</Heading>
                <p class="relative mt-2 flex-1 text-sm text-neutral-600">{{ story.description }}</p>
                <span class="relative mt-5 inline-flex items-center gap-1.5 text-xs font-medium text-neutral-900">
                    Read the story
                    <Icon name="ArrowRight01Icon" class="size-4 transition-transform group-hover:translate-x-0.5 group-focus-visible:translate-x-0.5" />
                </span>
            </Link>
        </li>
    </ul>
</template>
