<script setup>
import { setLayoutProps, Link } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SubjectImage from '../../Components/Subjects/SubjectImage.vue';
import Icon from '../../Components/Ui/Icon.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

defineProps({
    // [{label, segment, count, recent: [{name, url, cover}]}], one per kind.
    kinds: { type: Array, default: () => [] },
    og: { type: Object, default: () => ({}) },
});

setLayoutProps({
    breadcrumb: [{ label: 'Life' }],
});

// SubjectImage's fallback icon is keyed by the singular kind label, which the
// hub only knows in the plural.
const SINGULAR = {
    people: 'Person',
    pets: 'Pet',
    spots: 'Spot',
    things: 'Thing',
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

    <!-- A shelf per kind rather than four cover tiles: the hub names what is
         actually in it, and every face arrives properly cropped in its own
         frame instead of fighting its neighbours inside one mosaic. -->
    <section v-for="kind in kinds" :key="kind.segment" class="mt-12">
        <div class="mb-4 flex items-baseline justify-between gap-4">
            <h2 class="font-display text-section">{{ kind.label }}</h2>

            <Link
                :href="`/life/${kind.segment}`"
                class="group inline-flex shrink-0 items-center gap-1 text-meta text-neutral-500 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none"
            >
                All {{ kind.count }}
                <Icon name="ArrowRight01Icon" class="size-4 transition-transform group-hover:translate-x-0.5" />
            </Link>
        </div>

        <ul v-if="kind.recent.length" class="grid grid-cols-3 gap-x-4 gap-y-6 sm:grid-cols-4 lg:grid-cols-6">
            <li v-for="subject in kind.recent" :key="subject.url">
                <Link
                    :href="subject.url"
                    class="group block focus-visible:outline-none"
                >
                    <SubjectImage
                        :cover="subject.cover"
                        :name="subject.name"
                        :kind="SINGULAR[kind.segment]"
                        class="transition-opacity group-hover:opacity-90 group-focus-visible:ring-2 group-focus-visible:ring-accent-500"
                    />
                    <p class="mt-2 truncate text-meta font-medium text-neutral-900 underline-offset-4 group-hover:underline group-focus-visible:underline">{{ subject.name }}</p>
                </Link>
            </li>
        </ul>

        <p v-else class="text-meta text-neutral-500">Nothing here yet.</p>
    </section>
</template>
