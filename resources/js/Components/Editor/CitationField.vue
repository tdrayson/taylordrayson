<script setup>
import { ref, watch } from 'vue';
import { csrf } from '../../lib/csrf.js';
import { CONTROL, CONTROL_BORDER } from '../../lib/editor/control.js';
import ReplyContext from '../Entry/ReplyContext.vue';

const props = defineProps({
    id: { type: String, required: true },
    // The trimmed quote, empty when the fetched excerpt stands.
    modelValue: { type: String, default: '' },
    // The URL and kind from their own fields, which decide what to preview.
    responseUrl: { type: String, default: null },
    responseKind: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const preview = ref(null);
const loading = ref(false);
const failed = ref(false);

// The URL our last successful load resolved for, so a change can be told apart
// from the page's own first load of an existing reply.
const loadedFor = ref(null);

/**
 * Asks the server what the context will look like, through the same builder the
 * entry page uses. `refresh` refetches even when a copy is already stored.
 *
 * Changing the URL to a different post makes a quote taken from the old one
 * stale, so it is cleared here before the new excerpt arrives rather than left
 * to be saved against the wrong post.
 */
async function load(refresh = false) {
    if (! props.responseUrl || ! props.responseKind) {
        preview.value = null;
        loadedFor.value = null;

        return;
    }

    const changedUrl = loadedFor.value !== null && loadedFor.value !== props.responseUrl;

    if (changedUrl) {
        emit('update:modelValue', '');
    }

    loading.value = true;
    failed.value = false;

    try {
        const response = await fetch('/citations/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ url: props.responseUrl, kind: props.responseKind, refresh }),
        });

        if (! response.ok) {
            throw new Error(response.status);
        }

        preview.value = (await response.json()).data;
        loadedFor.value = props.responseUrl;

        // Pre-fill the quote from the excerpt: the first time for this field, or
        // whenever the url just changed and the old quote was cleared above.
        if (preview.value?.cited?.quote && (changedUrl || ! props.modelValue)) {
            emit('update:modelValue', preview.value.cited.quote);
        }
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

// Debounced, so typing a URL character by character does not fetch each prefix.
let timer = null;
watch(() => [props.responseUrl, props.responseKind], () => {
    clearTimeout(timer);
    timer = setTimeout(() => load(), 400);
}, { immediate: true });

/** Puts the fetched excerpt back after it has been trimmed or replaced. */
function reset() {
    emit('update:modelValue', preview.value?.cited?.quote ?? '');
}
</script>

<template>
    <div class="space-y-3">
        <div data-testid="citation-preview" :class="['transition-opacity', loading && 'opacity-50']">
            <ReplyContext v-if="preview" :response="preview" />
            <p v-else-if="failed" class="text-caption text-neutral-500">Could not read that page. It will be tried again when you save.</p>
        </div>

        <!-- A gesture answers with no words of its own, so it has nothing to quote. -->
        <textarea
            v-if="responseKind === 'reply'"
            :id="id"
            :value="modelValue"
            rows="3"
            maxlength="600"
            :class="[CONTROL, CONTROL_BORDER, 'text-neutral-900']"
            @input="emit('update:modelValue', $event.target.value)"
        />

        <p class="flex gap-4 text-caption text-neutral-500">
            <button v-if="responseKind === 'reply'" type="button" class="rounded-sm hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" @click="reset">Reset to excerpt</button>
            <button type="button" class="rounded-sm hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" @click="load(true)">Refresh</button>
        </p>
    </div>
</template>
