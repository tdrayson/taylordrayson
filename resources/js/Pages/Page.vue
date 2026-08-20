<script setup>
import { computed } from 'vue';
import { setLayoutProps, usePage, Link } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import BlockContent from '../Components/Ui/BlockContent.vue';
import EntryEditor from '../Components/Editor/EntryEditor.vue';
import { valuesFor } from '../lib/editor/defaults.js';
import Pill from '../Components/Ui/Pill.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    id: { type: Number, default: null },
    title: { type: String, required: true },
    excerpt: { type: String, default: null },
    content: { type: [Object, Array, String], default: null },
    published: { type: Boolean, default: true },
    editing: { type: Boolean, default: false },
    // Field definitions from FieldRegistry, driving the properties panel.
    fields: { type: Array, default: () => [] },
    og: { type: Object, default: () => ({}) },
    // Map of href -> preview data for internal content links.
    linkPreviews: { type: Object, default: () => ({}) },
    linkFavicons: { type: Object, default: () => ({}) },
    // kind:id -> {title, url, exists} for the mentions in the content.
    mentions: { type: Object, default: () => ({}) },
});

setLayoutProps({ minimal: props.editing, breadcrumb: [{ label: props.title }] });

const signedIn = computed(() => usePage().props.signedIn === true);

// Current values for the shared editor, read off the props this page already
// receives rather than a second copy of the record.
const editorValues = computed(() => valuesFor(props.fields, {
    title: props.title,
    excerpt: props.excerpt,
    content: props.content,
    published: props.published,
}));
</script>

<template>
    <AppHead :og="og" />

    <!-- Editing uses the same surface as every other type, so the page does
         not drift into having its own editor. -->
    <div v-if="editing">
        <EntryEditor
            :fields="fields"
            :values="editorValues"
            :action="`/entries/page/${id}`"
            />
    </div>

    <!-- No width cap here: the heading/excerpt carry their own measure below, and
         BlockContent's renderer already applies max-w-prose/max-w-media per
         block, so a narrower ancestor would clip the wider (media) blocks. -->
    <article v-else>
        <header>
            <!-- Unpublished pages are only visible to the logged-in owner; badge them so it's obvious. -->
            <Pill v-if="!published" label="Draft" variant="accent" class="mb-3" />
            <h1 v-twemoji class="max-w-2xl font-display text-display">{{ title }}</h1>
            <p v-if="excerpt" v-twemoji class="mt-3 max-w-prose text-body text-lg text-neutral-700">{{ excerpt }}</p>

            <Link v-if="signedIn" :href="`?edit`" class="mt-3 inline-block text-meta text-accent-500 underline underline-offset-2">
                Edit this page
            </Link>
        </header>

        <BlockContent :document="content" :link-previews="linkPreviews" :link-favicons="linkFavicons" class="mt-8" />
    </article>
</template>
