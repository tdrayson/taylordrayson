<script setup>
import { computed, ref } from 'vue';
import { Link, router, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import EntryEditor from '../Components/Editor/EntryEditor.vue';
import { valuesFor } from '../lib/editor/defaults.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    types: { type: Array, default: () => [] },
    // Null on the hub, a type slug once one is picked.
    type: { type: String, default: null },
    fields: { type: Array, default: () => [] },
});

setLayoutProps({ breadcrumb: [{ label: 'New' }] });

// Typing here starts a note without picking anything: the common case should
// cost no taps at all, and the tiles are for everything else.
const quickNote = ref('');

const values = computed(() => valuesFor(props.fields));

function startNote() {
    if (quickNote.value.trim() === '') {
        return;
    }

    router.post('/entries/note', { content: quickNote.value });
}
</script>

<template>
    <AppHead :og="{ title: 'New' }" />

    <div v-if="! type" class="max-w-2xl">
        <h1 class="font-display text-display">New</h1>

        <textarea
            v-model="quickNote"
            rows="3"
            placeholder="What's on your mind?"
            class="mt-6 w-full rounded-lg border border-neutral-100 bg-neutral-0 px-4 py-3 text-body text-neutral-900 placeholder:text-neutral-500 focus:border-accent-500 focus:outline-none"
            @keydown.meta.enter="startNote"
        />

        <div class="mt-2 flex justify-end">
            <button
                type="button"
                class="rounded-md bg-accent-500 px-4 py-2 text-meta font-semibold text-white transition-opacity disabled:opacity-40"
                :disabled="quickNote.trim() === ''"
                @click="startNote"
            >
                Save note
            </button>
        </div>

        <p class="mt-8 text-label uppercase text-neutral-500">Or start something else</p>

        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
            <Link
                v-for="entryType in types"
                :key="entryType.type"
                :href="`/new/${entryType.type}`"
                class="flex min-h-20 items-center justify-center rounded-lg border border-neutral-100 px-4 py-5 text-center text-meta font-medium text-neutral-900 transition-colors hover:border-accent-500 hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            >
                {{ entryType.label }}
            </Link>
        </div>
    </div>

    <div v-else>
        <div class="mb-2 flex items-baseline gap-3">
            <h1 class="font-display text-section">New {{ type }}</h1>
            <Link href="/new" class="text-meta text-accent-500 underline underline-offset-2">Pick another type</Link>
        </div>

        <EntryEditor
            :fields="fields"
            :values="values"
            :action="`/entries/${type}`"
            method="post"
            submit-label="Create"
        />
    </div>
</template>
