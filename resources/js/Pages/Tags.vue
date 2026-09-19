<script setup>
import { computed } from 'vue';
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Heading from '../Components/Ui/Heading.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    // [{ name, slug, url, count }], already name-ordered by the server.
    tags: { type: Array, default: () => [] },
});

setLayoutProps({ breadcrumb: [{ label: 'Tags' }] });

// The most-used tags, featured larger at the top with their counts.
const featured = computed(() =>
    [...props.tags].sort((a, b) => b.count - a.count).slice(0, 8),
);
</script>

<template>
    <AppHead :og="og" />

    <header>
        <Heading as="h1" size="display">Tags</Heading>
        <p v-if="tags.length" class="mt-2 text-sm text-neutral-500">{{ tags.length }} topics across the site</p>
    </header>

    <template v-if="tags.length">
        <!-- Most-tagged, featured large with a superscript count. -->
        <div class="mt-10 flex flex-wrap items-baseline gap-x-6 gap-y-3">
            <Link
                v-for="tag in featured"
                :key="tag.slug"
                :href="tag.url"
                class="font-display text-2xl font-semibold leading-tight tracking-tight text-neutral-800 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
            >{{ tag.name }}<sup class="ml-0.5 align-super text-2xs font-semibold tabular-nums text-neutral-400">{{ tag.count }}</sup></Link>
        </div>

        <!-- Everything A-Z as quiet inline text, each with its count. -->
        <div class="mt-12 flex flex-wrap gap-x-5 gap-y-2 border-t border-neutral-50 pt-8">
            <Link
                v-for="tag in tags"
                :key="tag.slug"
                :href="tag.url"
                class="text-sm text-neutral-600 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
            >{{ tag.name }} <span class="tabular-nums text-neutral-400">{{ tag.count }}</span></Link>
        </div>
    </template>

    <p v-else class="mt-10 text-sm text-neutral-500">No tags yet.</p>
</template>
