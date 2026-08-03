<script setup>
import { computed, ref } from 'vue';
import { setLayoutProps, useForm, usePage, Link } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import BlockContent from '../Components/Ui/BlockContent.vue';
import RichTextEditor from '../Components/Editor/RichTextEditor.vue';
import Button from '../Components/Ui/Button.vue';
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
    // kind:id -> {title, url, exists} for the mentions in the content.
    mentions: { type: Object, default: () => ({}) },
});

setLayoutProps({ breadcrumb: [{ label: props.title }] });

const signedIn = computed(() => usePage().props.signedIn === true);

// Preview swaps the form for the rendered output using the CURRENT buffer, so
// it shows unsaved work. A saved draft can already be previewed by visiting its
// own URL, which is why there is no preview route.
const previewing = ref(false);

// Properties live in a panel that is summoned rather than parked, so the
// writing column keeps the full width.
const showProperties = ref(false);

const form = useForm({
    title: props.title,
    excerpt: props.excerpt,
    content: Array.isArray(props.content) ? props.content : [],
    published: props.published,
});

const optionalFields = computed(() => props.fields.filter((field) => !field.primary));

function save() {
    form.patch(`/pages/${props.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppHead :og="og" />

    <!-- Editing: content keeps the full width and the properties panel slides
         in, rather than sitting open and halving the writing column. -->
    <div v-if="editing" class="relative">
        <div class="sticky top-0 z-10 -mx-4 mb-6 flex items-center justify-between gap-3 border-b border-neutral-50 bg-neutral-0/90 px-4 py-3 backdrop-blur">
            <p class="text-meta text-neutral-500">
                <span v-if="form.processing">Saving...</span>
                <span v-else-if="form.isDirty">Unsaved changes</span>
                <span v-else>Saved</span>
            </p>

            <div class="flex items-center gap-2">
                <Button size="sm" @click="previewing = !previewing">
                    {{ previewing ? 'Edit' : 'Preview' }}
                </Button>
                <Button size="sm" @click="showProperties = !showProperties">Properties</Button>
                <Button size="sm" variant="primary" :disabled="form.processing" @click="save">Save</Button>
            </div>
        </div>

        <article v-if="previewing">
            <h1 v-twemoji class="max-w-2xl font-display text-display">{{ form.title }}</h1>
            <p v-if="form.excerpt" class="mt-3 max-w-prose text-body text-lg text-neutral-700">{{ form.excerpt }}</p>
            <BlockContent :document="form.content" :link-previews="linkPreviews" class="mt-8" />
        </article>

        <article v-else>
            <input
                v-model="form.title"
                class="w-full max-w-2xl border-none bg-transparent p-0 font-display text-display text-neutral-900 focus:outline-none"
                placeholder="Title"
            >

            <RichTextEditor
                v-model="form.content"
                profile="document"
                placeholder="Write, or type @ to mention something"
                :resolved="mentions"
                class="mt-8"
            />
        </article>

        <aside
            v-if="showProperties"
            class="fixed right-4 top-24 z-20 w-72 rounded-lg border border-neutral-100 bg-neutral-0 p-4 shadow-lg"
        >
            <label class="flex items-center justify-between gap-3 text-meta text-neutral-900">
                Published
                <input v-model="form.published" type="checkbox" class="size-4 rounded border-neutral-100 text-accent-500">
            </label>

            <label class="mt-4 block text-label uppercase text-neutral-500">
                Excerpt
                <textarea
                    v-model="form.excerpt"
                    rows="3"
                    class="mt-1 w-full rounded-md border border-neutral-100 px-2 py-1.5 text-meta text-neutral-900 focus:border-accent-500 focus:outline-none"
                />
            </label>

            <p v-if="optionalFields.length" class="mt-4 text-label uppercase text-neutral-500">
                {{ optionalFields.length }} more field{{ optionalFields.length === 1 ? '' : 's' }} available
            </p>
        </aside>
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

        <BlockContent :document="content" :link-previews="linkPreviews" class="mt-8" />
    </article>
</template>
