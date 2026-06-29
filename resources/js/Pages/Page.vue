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
    draft: { type: Boolean, default: false },
    og: { type: Object, default: () => ({}) },
});

setLayoutProps({ breadcrumb: [{ label: props.title }] });
</script>

<template>
    <AppHead :og="og" />

    <article class="max-w-2xl">
        <header>
            <Pill v-if="draft" label="Draft" variant="accent" class="mb-3" />
            <h1 class="font-display text-display">{{ title }}</h1>
            <p v-if="excerpt" class="mt-3 text-body text-lg text-neutral-700">{{ excerpt }}</p>
        </header>

        <BlockContent :document="content" class="mt-8" />
    </article>
</template>
