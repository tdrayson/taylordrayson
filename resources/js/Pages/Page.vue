<script setup>
import { computed } from 'vue';
import { setLayoutProps, usePage, Link } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import BlockContent from '../Components/Ui/BlockContent.vue';
import EntryEditor from '../Components/Editor/EntryEditor.vue';
import { valuesFor } from '../lib/editor/defaults.js';
import { provideLinkContext } from '../lib/linkContext.js';
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
    // The record's own value per offered field, for the editor to start from.
    values: { type: Object, default: () => ({}) },
    og: { type: Object, default: () => ({}) },
    // Map of href -> preview data for internal content links.
    linkPreviews: { type: Object, default: () => ({}) },
    linkFavicons: { type: Object, default: () => ({}) },
    // kind:id -> {title, url, exists} for the mentions in the content.
    mentions: { type: Object, default: () => ({}) },
});

provideLinkContext(computed(() => ({ previews: props.linkPreviews, favicons: props.linkFavicons })));

setLayoutProps({ minimal: props.editing, breadcrumb: [{ label: props.title }] });

const signedIn = computed(() => usePage().props.signedIn === true);

// Straight off the record, not rebuilt from the display props: listing the
// keys by hand meant any field not on that list opened empty and was saved
// back empty.
const editorValues = computed(() => valuesFor(props.fields, props.values));
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
            <h1 v-twemoji class="max-w-2xl font-display text-display">{{ title }}</h1>
            <p v-if="excerpt" v-twemoji class="mt-3 max-w-prose text-body text-lg text-neutral-700">{{ excerpt }}</p>

            <!-- Both only mean anything to the owner, so they sit together
                 below the page rather than the badge interrupting the title.
                 Unpublished pages are visible to nobody else. -->
            <div v-if="signedIn || !published" class="mt-3 flex items-center gap-3">
                <Pill v-if="!published" label="Draft" variant="accent" />

                <Link v-if="signedIn" :href="`?edit`" class="text-meta text-accent-500 underline underline-offset-2">
                    Edit this page
                </Link>
            </div>
        </header>

        <BlockContent :document="content" class="mt-8" />
    </article>
</template>
