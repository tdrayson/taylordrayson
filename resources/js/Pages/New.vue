<script setup>
import { computed } from 'vue';
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import EntryEditor from '../Components/Editor/EntryEditor.vue';
import Icon from '../Components/Ui/Icon.vue';
import { valuesFor } from '../lib/editor/defaults.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    types: { type: Array, default: () => [] },
    // Null on the hub, a type slug once one is picked.
    type: { type: String, default: null },
    fields: { type: Array, default: () => [] },
});

// Home / New / Article, with New linking back to the picker: changing your
// mind should be the breadcrumb you already expect, not a separate link.
setLayoutProps({
    breadcrumb: props.type
        ? [{ label: 'New', href: '/new' }, { label: props.type.charAt(0).toUpperCase() + props.type.slice(1) }]
        : [{ label: 'New' }],
});

const values = computed(() => valuesFor(props.fields));

</script>

<template>
    <AppHead :og="{ title: 'New' }" />

    <div v-if="! type" class="max-w-2xl">
        <h1 class="font-display text-display">New</h1>

        <p class="mt-6 text-meta text-neutral-500">What are you adding?</p>

        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
            <Link
                v-for="entryType in types"
                :key="entryType.type"
                :href="`/new/${entryType.type}`"
                class="group flex min-h-24 flex-col items-center justify-center gap-2 rounded-lg border border-neutral-100 px-4 py-5 text-center text-meta font-medium text-neutral-900 transition-colors hover:border-accent-500 hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            >
                <Icon :icon="entryType.icon" class="size-6 text-neutral-500 transition-colors group-hover:text-accent-500" />
                {{ entryType.label }}
            </Link>
        </div>
    </div>

    <div v-else>
        <EntryEditor
            :fields="fields"
            :values="values"
            :action="`/entries/${type}`"
            method="post"
            submit-label="Post"
        />
    </div>
</template>
