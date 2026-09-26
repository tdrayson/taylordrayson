<script setup>
import { computed } from 'vue';
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import EntryEditor from '../Components/Editor/EntryEditor.vue';
import Icon from '../Components/Ui/Icon.vue';
import Heading from '../Components/Ui/Heading.vue';
import { valuesFor } from '../lib/editor/defaults.js';
import { claim } from '../lib/editor/handoff.js';
import { entryType } from '../entryTypes.js';

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
    minimal: true,
    breadcrumb: props.type
        ? [{ label: 'New', href: '/new' }, { label: props.type.charAt(0).toUpperCase() + props.type.slice(1) }]
        : [{ label: 'New' }],
});

// Anything handed over on the way here (a note outgrowing its cap, a duplicated
// entry), claimed once on setup rather than in the computed: claiming clears it,
// and a computed may run again.
const carried = claim(props.type);

const values = computed(() => ({ ...valuesFor(props.fields), ...carried }));

// Drawn in place of a title field, for the types without one.
const heading = computed(() => (props.type ? `New ${entryType(props.type).label}` : null));
</script>

<template>
    <AppHead :og="{ title: 'New' }" />

    <div v-if="! type" class="max-w-2xl">
        <Heading as="h1" size="display">New</Heading>

        <p class="mt-6 text-sm text-neutral-500">What are you adding?</p>

        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
            <Link
                v-for="entryType in types"
                :key="entryType.type"
                :href="`/new/${entryType.type}`"
                class="group flex min-h-24 flex-col items-center justify-center gap-2 rounded-lg border border-neutral-100 px-4 py-5 text-center text-sm font-medium text-neutral-900 transition-colors hover:border-accent-500 hover:bg-accent-50 hover:text-accent-700"
            >
                <Icon :icon="entryType.icon" class="size-6 text-neutral-500 transition-colors group-hover:text-accent-500" />
                {{ entryType.label }}
            </Link>
        </div>
    </div>

    <EntryEditor
        v-else
        :type="type"
        :fields="fields"
        :values="values"
        :action="`/entries/${type}`"
        method="post"
        submit-label="Post"
        :convert-to="type === 'note' ? 'article' : null"
        :heading="heading"
    />
</template>
