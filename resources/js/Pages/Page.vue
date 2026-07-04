<script setup>
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import BlockContent from '../Components/Ui/BlockContent.vue';
import Pill from '../Components/Ui/Pill.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    title: { type: String, required: true },
    excerpt: { type: String, default: null },
    content: { type: [Object, Array, String], default: null },
    published: { type: Boolean, default: true },
    og: { type: Object, default: () => ({}) },
});

setLayoutProps({ breadcrumb: [{ label: props.title }] });
</script>

<template>
    <AppHead :og="og" />

    <!-- No width cap here: the heading/excerpt carry their own measure below, and
         BlockContent's renderer already applies max-w-reading/max-w-media per
         block, so a narrower ancestor would clip the wider (media) blocks. -->
    <article>
        <header>
            <!-- Unpublished pages are only visible to the logged-in owner; badge them so it's obvious. -->
            <Pill v-if="!published" label="Draft" variant="accent" class="mb-3" />
            <h1 class="max-w-2xl font-display text-display">{{ title }}</h1>
            <p v-if="excerpt" class="mt-3 max-w-reading text-body text-lg text-neutral-700">{{ excerpt }}</p>
        </header>

        <BlockContent :document="content" class="mt-8" />
    </article>
</template>
