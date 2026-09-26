<script setup>
import { computed } from 'vue';
import { entryType } from '../../entryTypes.js';

/**
 * The editor's header, mirroring the entry page's: type label, title, date line.
 * A type with a title field edits it here; any other shows its heading read-only.
 */
const props = defineProps({
    type: { type: String, required: true },
    // The title field's definition, or null for a type without one.
    titleField: { type: Object, default: null },
    titleError: { type: String, default: null },
    // Shown in place of a title field, e.g. the card title or "New Fuel".
    heading: { type: String, default: null },
    // The entry's date as the page formats it; omitted when empty.
    date: { type: String, default: null },
});

const title = defineModel('title', { type: String, default: '' });

const meta = computed(() => entryType(props.type));
const accentStyle = computed(() => ({ color: `var(--color-${meta.value.accent})` }));
</script>

<template>
    <header>
        <p class="text-2xs font-semibold uppercase tracking-wider" :style="accentStyle">{{ meta.label }}</p>

        <!-- The page needs exactly one h1 for the outline, and a title field is
             an input rather than a heading, so it gets a hidden one. -->
        <template v-if="titleField">
            <h1 class="sr-only">{{ title || heading || 'Untitled' }}</h1>

            <!-- A textarea so long titles wrap; Enter stays blocked because a title is one line. -->
            <textarea
                :id="titleField.name"
                v-model="title"
                :placeholder="titleField.label"
                :aria-label="titleField.label"
                :aria-invalid="titleError ? 'true' : undefined"
                :aria-describedby="titleError ? `${titleField.name}-error` : undefined"
                rows="1"
                data-text-size
                class="field-sizing-content mt-1 w-full resize-none overflow-hidden border-none bg-transparent p-0 pb-1.5 font-display text-5xl font-extrabold tracking-tight text-neutral-900 placeholder:text-neutral-200 focus:outline-none"
                @keydown.enter.prevent
            />

            <p v-if="titleError" :id="`${titleField.name}-error`" class="mt-1 text-xs text-red-600">{{ titleError }}</p>
        </template>

        <h1 v-else-if="heading" v-twemoji class="mt-1 max-w-2xl font-display text-5xl font-extrabold tracking-tight">{{ heading }}</h1>

        <h1 v-else class="sr-only">{{ [meta.label, date].filter(Boolean).join(', ') }}</h1>

        <p v-if="date" class="mt-2 text-sm text-neutral-500">{{ date }}</p>
    </header>
</template>
