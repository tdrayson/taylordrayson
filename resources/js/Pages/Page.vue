<script setup>
import { setLayoutProps } from '../composables/useLayout.js';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import ProseBody from '../Components/Ui/ProseBody.vue';
import Pill from '../Components/Ui/Pill.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    title: { type: String, required: true },
    excerpt: { type: String, default: null },
    /** Rendered Bard HTML from the server (replaces the old Editor.js content prop). */
    bodyHtml: { type: String, default: '' },
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

        <ProseBody :html="bodyHtml" class="mt-8" />
    </article>
</template>
